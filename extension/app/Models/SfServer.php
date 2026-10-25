<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-server data of the extension.
 *
 * @property int $server_id
 * @property string|null $token
 * @property string|null $privilege
 */
class SfServer extends Model
{
    protected $table = 'sfmanager_servers';

    protected $fillable = ['server_id', 'token', 'privilege'];

    protected $hidden = ['token'];

    protected $casts = [
        'token' => 'encrypted',
    ];
}
