<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfBackup;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfServer;

/**
 * Save backups as tar.gz archives inside the server (via Wings), so they work
 * on any node and count towards the server's own disk space.
 *
 * Archive content: everything in .config/Epic/FactoryGame/Saved/SaveGames (saves, blueprints)
 * except ServerSettings.*.sav – restoring old passwords and options would be a nasty surprise.
 */
class BackupService
{
    public const DIR = '.sfmanager-backups';
    private const SOURCE = '.config/Epic/FactoryGame/Saved/SaveGames';
    public const AUTOMATIC = ['scheduled', 'before-restart', 'before-update', 'before-load', 'before-restore'];

    public function __construct(
        private DaemonFileRepository $files,
        private SatisfactoryService $satisfactory,
    ) {
    }

    /**
     * Saves the running game (if possible) and archives all saves.
     *
     * @throws SatisfactoryApiException
     */
    public function create(Server $server, string $reason): SfBackup
    {
        $session = null;
        try {
            $state = $this->satisfactory->status($server)['state'] ?? null;
            $session = ($state['isGameRunning'] ?? false) ? $state['activeSessionName'] : null;
            if ($session) {
                $this->satisfactory->saveBefore($server, 'backup');
            }
        } catch (SatisfactoryApiException) {
            // Server offline or no admin token: back up the files as they are.
        }

        $files = $this->files->setServer($server);
        try {
            try {
                $files->createDirectory(self::DIR, '/');
            } catch (DaemonConnectionException) {
                // Directory exists already.
            }

            $entries = array_filter(
                $files->getDirectory(self::SOURCE),
                fn (array $e) => !preg_match('/^ServerSettings\..*\.sav$/i', $e['name'] ?? '')
            );
            if (!$entries) {
                throw new SatisfactoryApiException('sfm_backup_empty', 'There are no saves to back up yet.', 409);
            }
            $archive = $files->compressFiles('/', array_values(array_map(fn ($e) => self::SOURCE . '/' . $e['name'], $entries)));
            $name = now()->format('Y-m-d_His') . '_' . $reason . '.tar.gz';
            $target = self::DIR . '/' . $name;
            $files->renameFiles('/', [['from' => $archive['name'], 'to' => $target]]);
        } catch (DaemonConnectionException $e) {
            throw new SatisfactoryApiException('sfm_backup_failed', 'Creating the backup failed: ' . $e->getMessage(), 502, $e);
        }

        $backup = SfBackup::query()->create([
            'server_id' => $server->id,
            'file' => $target,
            'size' => (int) ($archive['size'] ?? 0),
            'reason' => $reason,
            'session' => $session,
        ]);

        $record = SfServer::for($server->id);
        $record->last_backup_at = now();
        $record->save();

        if (in_array($reason, self::AUTOMATIC, true)) {
            $this->rotate($server);
        }

        return $backup;
    }

    /**
     * Puts the saves from a backup back in place. Saves with the same name are
     * overwritten, newer saves stay. A safety backup is made first.
     */
    public function restore(Server $server, SfBackup $backup): void
    {
        $this->create($server, 'before-restore');

        try {
            $this->files->setServer($server)->decompressFile('/', $backup->file);
        } catch (DaemonConnectionException $e) {
            throw new SatisfactoryApiException('sfm_restore_failed', 'Restoring the backup failed: ' . $e->getMessage(), 502, $e);
        }
    }

    public function delete(Server $server, SfBackup $backup): void
    {
        try {
            $this->files->setServer($server)->deleteFiles('/', [$backup->file]);
        } catch (DaemonConnectionException) {
            // File already gone – remove the entry anyway.
        }
        $backup->delete();
    }

    /** Keeps only the newest N automatic backups; manual ones are never removed automatically. */
    public function rotate(Server $server): void
    {
        $keep = max(1, (int) AutomationSettings::get($server)['backup']['keep']);

        SfBackup::query()
            ->where('server_id', $server->id)
            ->whereIn('reason', self::AUTOMATIC)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get()
            ->each(fn (SfBackup $b) => $this->delete($server, $b));
    }
}
