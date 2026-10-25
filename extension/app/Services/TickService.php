<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfServer;

/**
 * Runs every minute (sfmanager:tick): metrics, crash watchdog, scheduled restarts and backups.
 * Each server is handled on its own, so one broken server never stops the others.
 */
class TickService
{
    /** Do not judge a server that has just started – loading can take minutes. */
    private const STARTUP_GRACE_SECONDS = 300;
    /** Minimum pause between two watchdog restarts. */
    private const WATCHDOG_COOLDOWN_MINUTES = 15;

    public function __construct(
        private GlobalSettings $global,
        private SatisfactoryService $satisfactory,
        private MetricsService $metrics,
        private BackupService $backups,
        private DaemonPowerRepository $power,
    ) {
    }

    /** @return string[] log lines */
    public function run(): array
    {
        $lock = Cache::lock('sfmanager:tick', 300);
        if (!$lock->get()) {
            return ['Previous tick still running – skipped.'];
        }

        $log = [];
        try {
            foreach ($this->global->servers()->with(['allocation', 'node'])->get() as $server) {
                try {
                    $log[] = $server->uuidShort . ': ' . $this->tickServer($server);
                } catch (\Throwable $e) {
                    Log::warning('[sfmanager] tick failed for server ' . $server->uuid, ['exception' => $e]);
                    $log[] = $server->uuidShort . ': error – ' . $e->getMessage();
                }
            }
        } finally {
            $lock->release();
        }

        return $log;
    }

    private function tickServer(Server $server): string
    {
        $wings = app(Wings::class)->setServer($server)->state();
        if (!$wings) {
            return 'wings unreachable';
        }

        $running = $wings['state'] === 'running';
        $status = $running
            ? $this->satisfactory->status($server)
            : ['reachable' => false, 'state' => null, 'access' => 'none', 'claimed' => null];

        if ($this->global->enabled('metrics')) {
            $this->metrics->record($server, $status);
        }

        $record = SfServer::for($server->id);
        $settings = AutomationSettings::get($server);
        $done = [];

        if ($this->global->enabled('watchdog') && $settings['watchdog']['enabled']) {
            $done[] = $this->watchdog($server, $record, $settings, $wings, $status);
        }
        if ($running && $this->global->enabled('restarts') && $settings['restart']['times']) {
            $done[] = $this->scheduledRestart($server, $record, $settings, $status);
        }
        if ($running && $this->global->enabled('backups') && $settings['backup']['interval_hours'] > 0) {
            $done[] = $this->scheduledBackup($server, $record, $settings);
        }

        return implode(', ', array_filter([$wings['state'], ...$done]));
    }

    /** Restarts a server whose process runs but whose API stopped answering. */
    private function watchdog(Server $server, SfServer $record, array $settings, array $wings, array $status): ?string
    {
        if ($wings['state'] !== 'running' || $wings['uptime'] < self::STARTUP_GRACE_SECONDS || $status['reachable']) {
            if ($record->watchdog_failures !== 0) {
                $record->watchdog_failures = 0;
                $record->save();
            }

            return null;
        }

        $record->watchdog_failures++;
        $record->save();

        $limit = max(1, (int) $settings['watchdog']['minutes']);
        $cooling = $record->watchdog_action_at && $record->watchdog_action_at->gt(now()->subMinutes(self::WATCHDOG_COOLDOWN_MINUTES));
        if ($record->watchdog_failures < $limit || $cooling) {
            return "watchdog {$record->watchdog_failures}/{$limit}";
        }

        $lastLines = app(Wings::class)->setServer($server)->logs(15);
        $this->power->setServer($server)->send('restart');

        $record->watchdog_failures = 0;
        $record->watchdog_action_at = now();
        $record->save();

        Activity::event('server:sfmanager.watchdog-restart')
            ->subject($server)
            ->property('log', implode("\n", $lastLines))
            ->log();

        return 'watchdog restarted server';
    }

    /** "04:00" in the panel time zone; each slot runs at most once per day. */
    private function scheduledRestart(Server $server, SfServer $record, array $settings, array $status): ?string
    {
        $now = now(config('app.timezone'));
        $time = $now->format('H:i');
        if (!in_array($time, $settings['restart']['times'], true)) {
            return null;
        }

        $slot = $now->format('Y-m-d') . ' ' . $time;
        if ($record->last_restart_slot === $slot) {
            return null;
        }
        $record->last_restart_slot = $slot;
        $record->save();

        $players = (int) ($status['state']['numConnectedPlayers'] ?? 0);
        if ($settings['restart']['only_empty'] && $players > 0) {
            return "restart skipped ($players online)";
        }

        if ($settings['backup']['before_restart'] && $this->global->enabled('backups')) {
            $this->backups->create($server, 'before-restart');
        } else {
            try {
                $this->satisfactory->saveBefore($server, 'before-restart');
            } catch (SatisfactoryApiException) {
                // No game running or no admin token: restart anyway.
            }
        }

        $this->power->setServer($server)->send('restart');
        Activity::event('server:sfmanager.scheduled-restart')->subject($server)->property('slot', $slot)->log();

        return 'scheduled restart';
    }

    private function scheduledBackup(Server $server, SfServer $record, array $settings): ?string
    {
        $hours = (int) $settings['backup']['interval_hours'];
        if ($record->last_backup_at && $record->last_backup_at->gt(now()->subHours($hours)->addMinute())) {
            return null;
        }

        $backup = $this->backups->create($server, 'scheduled');

        return 'backup ' . basename($backup->file);
    }
}
