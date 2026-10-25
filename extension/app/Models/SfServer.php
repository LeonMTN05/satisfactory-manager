<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-server data of the extension.
 *
 * @property int $server_id
 * @property string|null $token
 * @property string|null $privilege
 * @property array|null $settings
 * @property \Carbon\Carbon|null $last_backup_at
 * @property int $watchdog_failures
 * @property \Carbon\Carbon|null $watchdog_action_at
 * @property string|null $last_restart_slot
 */
class SfServer extends Model
{
    protected $table = 'sfmanager_servers';

    protected $fillable = ['server_id', 'token', 'privilege', 'settings'];

    protected $hidden = ['token'];

    protected $casts = [
        'token' => 'encrypted',
        'settings' => 'array',
        'last_backup_at' => 'datetime',
        'watchdog_action_at' => 'datetime',
    ];

    public static function for(int $serverId): self
    {
        return static::query()->firstOrCreate(['server_id' => $serverId]);
    }
}
