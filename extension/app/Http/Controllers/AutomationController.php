<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfBackup;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfMetric;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\BackupService;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\UpdateService;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\GlobalSettings;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\MetricsService;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\AutomationSettings;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\SatisfactoryApiException;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\BranchRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ViewStatusRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ListBackupsRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\CreateBackupRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\DeleteBackupRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\UpdateServerRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\RestoreBackupRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ReadAutomationRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\UpdateAutomationRequest;

class AutomationController extends ClientApiController
{
    public function __construct(
        private GlobalSettings $global,
        private BackupService $backups,
        private UpdateService $updates,
        private MetricsService $metrics,
        private DaemonPowerRepository $power,
    ) {
        parent::__construct();
    }

    /** Which automation features the admin has enabled panel-wide. */
    private function features(): array
    {
        return [
            'metrics' => $this->global->enabled('metrics'),
            'backups' => $this->global->enabled('backups'),
            'restarts' => $this->global->enabled('restarts'),
            'watchdog' => $this->global->enabled('watchdog'),
            'update_check' => $this->global->enabled('update_check'),
        ];
    }

    public function show(ReadAutomationRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'settings' => AutomationSettings::get($server),
            'timezone' => config('app.timezone'),
            'features' => $this->features(),
            'update' => $this->updates->info($server),
        ]);
    }

    public function update(UpdateAutomationRequest $request, Server $server): JsonResponse
    {
        $input = $request->validated();
        $input['restart']['times'] = array_values(array_unique($input['restart']['times'] ?? []));
        sort($input['restart']['times']);

        $settings = AutomationSettings::save($server, $input);
        Activity::event('server:sfmanager.automation')->property('settings', $settings)->log();

        return new JsonResponse(['settings' => $settings]);
    }

    // ---- Backups ----------------------------------------------------------------

    public function backups(ListBackupsRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'backups' => SfBackup::query()->where('server_id', $server->id)->orderByDesc('created_at')->orderByDesc('id')->get(),
            'automatic_reasons' => BackupService::AUTOMATIC,
        ]);
    }

    public function createBackup(CreateBackupRequest $request, Server $server): JsonResponse
    {
        $backup = $this->backups->create($server, 'manual');
        Activity::event('server:sfmanager.backup')->property('file', $backup->file)->log();

        return new JsonResponse($backup);
    }

    private function findBackup(Server $server, int $id): SfBackup
    {
        return SfBackup::query()->where('server_id', $server->id)->findOrFail($id);
    }

    public function restoreBackup(RestoreBackupRequest $request, Server $server, int $backup): Response
    {
        $model = $this->findBackup($server, $backup);
        $this->backups->restore($server, $model);
        Activity::event('server:sfmanager.backup-restore')->property('file', $model->file)->log();

        return $this->returnNoContent();
    }

    public function deleteBackup(DeleteBackupRequest $request, Server $server, int $backup): Response
    {
        $model = $this->findBackup($server, $backup);
        $this->backups->delete($server, $model);
        Activity::event('server:sfmanager.backup-delete')->property('file', $model->file)->log();

        return $this->returnNoContent();
    }

    // ---- Metrics ----------------------------------------------------------------

    public function metrics(ViewStatusRequest $request, Server $server): JsonResponse
    {
        $range = (string) $request->query('range', '24h');

        return new JsonResponse($this->metrics->history($server, $range));
    }

    // ---- Updates ----------------------------------------------------------------

    /** Backup (if enabled) and restart – the egg installs updates on start. */
    public function runUpdate(UpdateServerRequest $request, Server $server): JsonResponse
    {
        if (!$this->updates->autoUpdate($server)) {
            throw new SatisfactoryApiException('sfm_auto_update_off', 'Automatic updates are disabled for this server (variable AUTO_UPDATE).', 409);
        }

        $backup = null;
        $settings = AutomationSettings::get($server);
        if ($settings['backup']['before_update'] && $this->global->enabled('backups')) {
            $backup = $this->backups->create($server, 'before-update')->file;
        }

        $this->power->setServer($server)->send('restart');
        Activity::event('server:sfmanager.update')->property('backup', $backup)->log();

        return new JsonResponse(['backup' => $backup]);
    }

    public function branch(BranchRequest $request, Server $server): Response
    {
        $this->updates->setBranch($server, $request->input('branch'));
        Activity::event('server:sfmanager.branch')->property('branch', $request->input('branch'))->log();

        return $this->returnNoContent();
    }

    // ---- Server list badges -----------------------------------------------------

    /** Latest data point of every Satisfactory server the user can access (no live API calls). */
    public function badges(Request $request): JsonResponse
    {
        if (!$this->global->enabled('metrics')) {
            return new JsonResponse(['servers' => (object) []]);
        }

        $servers = $request->user()->accessibleServers()
            ->whereIn('servers.egg_id', $this->global->eggIds())
            ->pluck('servers.uuidShort', 'servers.id');

        $result = [];
        foreach ($servers as $id => $short) {
            $metric = SfMetric::query()
                ->where('server_id', $id)
                ->where('created_at', '>=', now()->subMinutes(3))
                ->latest('id')
                ->first();
            if ($metric) {
                $result[$short] = $metric->only(['online', 'players', 'player_limit', 'tech_tier']);
            }
        }

        return new JsonResponse(['servers' => (object) $result]);
    }
}
