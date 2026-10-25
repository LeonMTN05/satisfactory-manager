<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models;

use Illuminate\Database\Eloquent\Model;

class SfBackup extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sfmanager_backups';

    protected $guarded = ['id'];

    protected $casts = ['size' => 'integer'];
}
