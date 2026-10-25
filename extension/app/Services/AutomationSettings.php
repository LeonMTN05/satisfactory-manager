<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfServer;

/**
 * Per-server automation settings with defaults.
 */
class AutomationSettings
{
    public const DEFAULTS = [
        'backup' => [
            'interval_hours' => 0,      // 0 = no scheduled backups
            'keep' => 10,               // automatic backups to keep
            'before_restart' => true,
            'before_update' => true,
            'before_load' => false,
        ],
        'restart' => [
            'times' => [],              // ["04:00"], panel time zone
            'only_empty' => false,
        ],
        'watchdog' => [
            'enabled' => false,
            'minutes' => 3,             // unresponsive for this long → restart
        ],
    ];

    public static function get(Server $server): array
    {
        return self::merge(SfServer::for($server->id)->settings ?? []);
    }

    public static function save(Server $server, array $settings): array
    {
        $record = SfServer::for($server->id);
        $record->settings = self::merge($settings);
        $record->save();

        return $record->settings;
    }

    /** Fills in defaults and drops unknown keys. */
    private static function merge(array $values): array
    {
        $result = [];
        foreach (self::DEFAULTS as $group => $defaults) {
            foreach ($defaults as $key => $default) {
                $result[$group][$key] = $values[$group][$key] ?? $default;
            }
        }

        return $result;
    }
}
