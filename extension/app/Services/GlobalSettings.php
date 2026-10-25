<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Console\BlueprintConsoleLibrary;

/**
 * Panel-wide settings of the extension (admin page), stored via Blueprint's settings table.
 */
class GlobalSettings
{
    public const DEFAULTS = [
        'eggs' => null,             // null = auto-detect
        'metrics' => true,
        'retention_days' => 30,
        'backups' => true,
        'restarts' => true,
        'watchdog' => true,
        'update_check' => true,     // asks api.steamcmd.net for the newest build
    ];

    public function __construct(private BlueprintConsoleLibrary $blueprint)
    {
    }

    public function get(string $key): mixed
    {
        return $this->blueprint->dbGet('sfmanager', $key, self::DEFAULTS[$key] ?? null);
    }

    public function all(): array
    {
        $values = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function set(string $key, mixed $value): void
    {
        $this->blueprint->dbSet('sfmanager', $key, $value);
    }

    public function enabled(string $feature): bool
    {
        return (bool) $this->get($feature);
    }

    /** Egg IDs treated as Satisfactory: chosen by the admin or detected automatically. */
    public function eggIds(): array
    {
        $chosen = $this->get('eggs');

        return is_array($chosen) ? array_map('intval', $chosen) : $this->detectEggIds();
    }

    /** Eggs that start FactoryGame or install Steam app 1690800. */
    public function detectEggIds(): array
    {
        return Egg::query()
            ->where('startup', 'like', '%FactoryGame%')
            ->orWhereHas('variables', fn (Builder $q) => $q->where('env_variable', 'SRCDS_APPID')->where('default_value', '1690800'))
            ->pluck('id')
            ->all();
    }

    public function isSatisfactory(Server $server): bool
    {
        return in_array((int) $server->egg_id, $this->eggIds(), true);
    }

    /** Running-capable Satisfactory servers (not suspended, not installing). */
    public function servers(): Builder
    {
        return Server::query()->whereIn('egg_id', $this->eggIds())->whereNull('status');
    }
}
