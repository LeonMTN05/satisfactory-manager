<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfServer;

/**
 * Talks to the Satisfactory server behind a Pterodactyl server.
 *
 * Security: we only ever connect to the server's own primary allocation,
 * so users cannot make the panel send requests to arbitrary hosts.
 */
class SatisfactoryService
{
    private const STATUS_CACHE_SECONDS = 5;

    /** Host and port of the HTTPS API (same as the game port). */
    public function address(Server $server): array
    {
        $allocation = $server->allocation;
        $host = $allocation->ip;

        // 0.0.0.0 / :: means "all interfaces" – reach it through the node instead.
        if (in_array($host, ['0.0.0.0', '::', '[::]'], true)) {
            $host = $server->node->fqdn;
        }

        return [$host, (int) $allocation->port];
    }

    public function api(Server $server, ?string $token = null): SatisfactoryApi
    {
        [$host, $port] = $this->address($server);

        return new SatisfactoryApi($host, $port, $token);
    }

    // ---- Token storage --------------------------------------------------------

    private function record(Server $server): ?SfServer
    {
        return SfServer::query()->where('server_id', $server->id)->first();
    }

    public function storedToken(Server $server): ?string
    {
        return $this->record($server)?->token;
    }

    public function storeToken(Server $server, string $token): void
    {
        SfServer::query()->updateOrCreate(
            ['server_id' => $server->id],
            ['token' => $token, 'privilege' => SatisfactoryApi::privilegeOf($token)],
        );
        $this->forgetStatus($server);
    }

    public function forgetToken(Server $server): void
    {
        SfServer::query()->where('server_id', $server->id)->update(['token' => null, 'privilege' => null]);
        $this->forgetStatus($server);
    }

    private function forgetStatus(Server $server): void
    {
        Cache::forget($this->statusKey($server));
    }

    private function statusKey(Server $server): string
    {
        return 'sfmanager:status:' . $server->id;
    }

    /** Tries a passwordless login; returns null if it is not possible. */
    private function passwordless(SatisfactoryApi $api, string $level): ?string
    {
        try {
            return $api->call('PasswordlessLogin', ['MinimumPrivilegeLevel' => $level])['authenticationToken'] ?? null;
        } catch (SatisfactoryApiException $e) {
            if ($e->errorCode === 'sfm_unreachable') {
                throw $e;
            }

            return null;
        }
    }

    /**
     * API client with admin rights: the stored token, or – on an unclaimed
     * server – a passwordless InitialAdmin token.
     *
     * @throws SatisfactoryApiException
     */
    public function adminApi(Server $server): SatisfactoryApi
    {
        $api = $this->api($server);

        if ($token = $this->storedToken($server)) {
            return $api->withToken($token);
        }
        if ($token = $this->passwordless($api, 'InitialAdmin')) {
            return $api->withToken($token);
        }

        throw SatisfactoryApiException::loginRequired();
    }

    // ---- Status ---------------------------------------------------------------

    /**
     * Collects everything the status card needs.
     *
     * access: admin   – stored admin token works
     *         initial – server is not claimed yet
     *         client  – no admin token, but the state is readable (no player password)
     *         none    – state not readable without login
     */
    public function status(Server $server): array
    {
        return Cache::remember($this->statusKey($server), self::STATUS_CACHE_SECONDS, fn () => $this->fetchStatus($server));
    }

    private function fetchStatus(Server $server): array
    {
        $api = $this->api($server);
        $result = ['reachable' => false, 'claimed' => null, 'access' => 'none', 'state' => null];

        try {
            $api->call('HealthCheck', ['ClientCustomData' => '']);
        } catch (SatisfactoryApiException $e) {
            return $result;
        }
        $result['reachable'] = true;

        $token = $this->storedToken($server);
        $access = 'admin';

        if ($token) {
            try {
                $result['state'] = $api->withToken($token)->call('QueryServerState')['serverGameState'] ?? null;
            } catch (SatisfactoryApiException $e) {
                if ($e->errorCode === 'sfm_unreachable') {
                    $result['reachable'] = false;

                    return $result;
                }
                // Token no longer valid (e.g. admin password changed).
                $this->forgetToken($server);
                $token = null;
            }
        }

        if (!$token) {
            if ($token = $this->passwordless($api, 'InitialAdmin')) {
                $access = 'initial';
            } elseif ($token = $this->passwordless($api, 'Client')) {
                $access = 'client';
            }

            if ($token) {
                $result['state'] = $api->withToken($token)->call('QueryServerState')['serverGameState'] ?? null;
            } else {
                $access = 'none';
            }
        }

        $result['access'] = $access;
        $result['claimed'] = $access !== 'initial';

        return $result;
    }

    // ---- Actions --------------------------------------------------------------

    /**
     * Claims an unclaimed server, optionally sets a player password and starts a new game.
     * Once the claim worked, later steps do not fail the whole request: their errors
     * are returned as warnings (error codes) so the user knows what is left to do.
     *
     * @return string[] error codes of follow-up steps that failed
     *
     * @throws SatisfactoryApiException
     */
    public function claim(Server $server, string $serverName, string $adminPassword, ?string $clientPassword, ?array $newGame): array
    {
        $api = $this->api($server);
        $initial = $this->passwordless($api, 'InitialAdmin');
        if (!$initial) {
            throw new SatisfactoryApiException('sfm_already_claimed', 'This server has already been claimed.', 409);
        }

        $token = $api->withToken($initial)->call('ClaimServer', [
            'ServerName' => $serverName,
            'AdminPassword' => $adminPassword,
        ])['authenticationToken'] ?? null;

        if (!$token) {
            throw new SatisfactoryApiException('sfm_claim_failed', 'The server did not return an admin token.', 502);
        }
        $this->storeToken($server, $token);

        $warnings = [];
        if ($clientPassword) {
            try {
                $api->withToken($token)->call('SetClientPassword', ['Password' => $clientPassword]);
            } catch (SatisfactoryApiException $e) {
                $warnings[] = 'sfm_client_password_failed';
            }
        }
        if ($newGame) {
            try {
                $this->newGame($server, $newGame['session_name'], $newGame['starting_location'], (bool) ($newGame['skip_onboarding'] ?? false));
            } catch (SatisfactoryApiException $e) {
                $warnings[] = 'sfm_new_game_failed';
            }
        }

        return $warnings;
    }

    /**
     * Creates and loads a new game. The server only answers once the map is loaded (~15 s or more).
     *
     * @throws SatisfactoryApiException
     */
    public function newGame(Server $server, string $sessionName, string $startingLocation, bool $skipOnboarding): void
    {
        $api = $this->adminApi($server);
        try {
            $api->call('CreateNewGame', ['NewGameData' => [
                'SessionName' => $sessionName,
                'MapName' => '',
                'StartingLocation' => $startingLocation,
                'bSkipOnboarding' => $skipOnboarding,
                'AdvancedGameSettings' => (object) [],
                'CustomOptionsOnlyForModding' => (object) [],
                // Required since late-2026 builds; an empty object means "default game mode".
                'GameModeSettings' => (object) [],
            ]], 50);
        } catch (SatisfactoryApiException $e) {
            // nginx cuts requests at 60 s. A timeout here means the map is still loading,
            // which the status card will show – only real errors are passed on.
            if ($e->errorCode !== 'sfm_unreachable') {
                throw $e;
            }
        }
        $this->forgetStatus($server);
    }

    /** @throws SatisfactoryApiException */
    public function login(Server $server, string $password): string
    {
        $token = $this->api($server)->call('PasswordLogin', [
            'MinimumPrivilegeLevel' => 'Administrator',
            'Password' => $password,
        ])['authenticationToken'] ?? null;

        if (!$token) {
            throw new SatisfactoryApiException('sfm_login_failed', 'The server did not return a token.', 502);
        }
        $this->storeToken($server, $token);

        return SatisfactoryApi::privilegeOf($token) ?? 'Administrator';
    }

    /** @throws SatisfactoryApiException */
    public function save(Server $server, string $name): void
    {
        $api = $this->adminApi($server);

        // SaveGame never answers when no game is loaded, so check first.
        $state = $api->call('QueryServerState')['serverGameState'] ?? [];
        if (empty($state['isGameRunning'])) {
            throw new SatisfactoryApiException('game_not_running', 'No game is running on the server.', 409);
        }

        // Saving a big factory can take a while.
        $api->call('SaveGame', ['SaveName' => $name], 50);
        $this->forgetStatus($server);
    }

    // ---- Connection info ------------------------------------------------------

    /** Address for players plus a TCP check of the game and reliable ports, seen from the panel. */
    public function connection(Server $server): array
    {
        [$host, $port] = $this->address($server);
        $allocation = $server->allocation;

        $reliable = (int) ($server->variables->firstWhere('env_variable', 'RELIABLE_PORT')?->server_value ?: 8888);
        $reliableAllocated = $server->allocations->contains(
            fn ($a) => (int) $a->port === $reliable && $a->ip === $allocation->ip
        );

        return [
            'address' => $allocation->ip_alias ?: $allocation->ip,
            'port' => (int) $allocation->port,
            'reliable_port' => $reliable,
            'reliable_port_allocated' => $reliableAllocated,
            'checks' => [
                'game_tcp' => $this->tcpOpen($host, $port),
                'reliable_tcp' => $this->tcpOpen($host, $reliable),
            ],
        ];
    }

    private function tcpOpen(string $host, int $port): bool
    {
        $h = str_contains($host, ':') ? "[{$host}]" : $host;
        $socket = @stream_socket_client("tcp://{$h}:{$port}", $errno, $errstr, 2);
        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }
}
