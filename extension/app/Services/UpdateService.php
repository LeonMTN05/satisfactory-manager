<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use GuzzleHttp\Client;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Compares the installed Steam build with the newest one and switches branches.
 */
class UpdateService
{
    public const APP_ID = '1690800';
    public const BRANCHES = ['public', 'experimental'];

    public function __construct(private DaemonFileRepository $files, private GlobalSettings $global)
    {
    }

    /** Build id from steamapps/appmanifest_1690800.acf, or null if unknown. */
    public function installedBuild(Server $server): ?string
    {
        try {
            $manifest = $this->files->setServer($server)->getContent('steamapps/appmanifest_' . self::APP_ID . '.acf', 64 * 1024);
        } catch (DaemonConnectionException|\Throwable) {
            return null;
        }

        return preg_match('/"buildid"\s+"(\d+)"/', $manifest, $m) ? $m[1] : null;
    }

    /** Newest build per branch from api.steamcmd.net (cached for an hour). */
    public function latestBuilds(): array
    {
        if (!$this->global->enabled('update_check')) {
            return [];
        }

        return Cache::remember('sfmanager:steam-builds', 3600, function () {
            try {
                $res = (new Client(['timeout' => 8]))->get('https://api.steamcmd.net/v1/info/' . self::APP_ID);
                $branches = json_decode((string) $res->getBody(), true)['data'][self::APP_ID]['depots']['branches'] ?? [];
            } catch (\Throwable) {
                return [];
            }

            $result = [];
            foreach (self::BRANCHES as $branch) {
                if (isset($branches[$branch]['buildid'])) {
                    $result[$branch] = [
                        'build' => (string) $branches[$branch]['buildid'],
                        'updated_at' => isset($branches[$branch]['timeupdated']) ? (int) $branches[$branch]['timeupdated'] : null,
                    ];
                }
            }

            return $result;
        });
    }

    private function variable(Server $server, string $env): ?object
    {
        return $server->variables->firstWhere('env_variable', $env);
    }

    /** Current branch from the egg variable SRCDS_BETAID (empty = public). */
    public function branch(Server $server): string
    {
        $value = $this->variable($server, 'SRCDS_BETAID')?->server_value;

        return $value ?: 'public';
    }

    public function autoUpdate(Server $server): bool
    {
        return $this->variable($server, 'AUTO_UPDATE')?->server_value === '1';
    }

    public function info(Server $server): array
    {
        $latest = $this->latestBuilds();
        $branch = $this->branch($server);
        $installed = $this->installedBuild($server);
        $newest = $latest[$branch]['build'] ?? null;

        return [
            'installed' => $installed,
            'branch' => $branch,
            'latest' => (object) $latest,
            'update_available' => $installed && $newest ? $installed !== $newest : null,
            'auto_update' => $this->autoUpdate($server),
            'branch_editable' => (bool) $this->variable($server, 'SRCDS_BETAID')?->user_editable,
            'check_enabled' => $this->global->enabled('update_check'),
        ];
    }

    /**
     * Sets SRCDS_BETAID. Takes effect with the next update on server start.
     *
     * @throws SatisfactoryApiException
     */
    public function setBranch(Server $server, string $branch): void
    {
        $variable = $this->variable($server, 'SRCDS_BETAID');
        if (!$variable || !$variable->user_editable) {
            throw new SatisfactoryApiException('sfm_branch_locked', 'The branch variable cannot be changed for this server.', 403);
        }

        ServerVariable::query()->updateOrCreate(
            ['server_id' => $server->id, 'variable_id' => $variable->id],
            ['variable_value' => $branch === 'public' ? '' : $branch],
        );
    }
}
