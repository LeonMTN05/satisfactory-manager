<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfMetric;

/**
 * Stores one data point per server and minute and returns it as chart data.
 */
class MetricsService
{
    /** Range → [period in hours, bucket size in minutes] */
    public const RANGES = ['24h' => [24, 10], '7d' => [168, 60], '30d' => [720, 240]];

    public function record(Server $server, array $status): void
    {
        $state = $status['reachable'] ? ($status['state'] ?? null) : null;

        SfMetric::query()->create([
            'server_id' => $server->id,
            'online' => (bool) $status['reachable'],
            'players' => $state['numConnectedPlayers'] ?? null,
            'player_limit' => $state['playerLimit'] ?? null,
            'tick_rate' => isset($state['averageTickRate']) ? round($state['averageTickRate'], 2) : null,
            'tech_tier' => ($state['isGameRunning'] ?? false) ? ($state['techTier'] ?? null) : null,
        ]);
    }

    /** Averaged buckets: time, max players, average tick rate, max tier, share online. */
    public function history(Server $server, string $range): array
    {
        [$hours, $bucket] = self::RANGES[$range] ?? self::RANGES['24h'];
        $seconds = $bucket * 60;

        $rows = SfMetric::query()
            ->where('server_id', $server->id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get([
                DB::raw("FLOOR(UNIX_TIMESTAMP(created_at) / $seconds) * $seconds AS bucket"),
                DB::raw('MAX(players) AS players'),
                DB::raw('AVG(tick_rate) AS tick_rate'),
                DB::raw('MAX(tech_tier) AS tech_tier'),
                DB::raw('AVG(online) AS online'),
            ]);

        return [
            'range' => $range,
            'bucket_minutes' => $bucket,
            'points' => $rows->map(fn ($r) => [
                't' => (int) $r->bucket,
                'players' => $r->players !== null ? (int) $r->players : null,
                'tick_rate' => $r->tick_rate !== null ? round((float) $r->tick_rate, 1) : null,
                'tech_tier' => $r->tech_tier !== null ? (int) $r->tech_tier : null,
                'online' => round((float) $r->online, 2),
            ])->all(),
        ];
    }

    public function prune(int $days): int
    {
        return SfMetric::query()->where('created_at', '<', now()->subDays(max(1, $days)))->delete();
    }
}
