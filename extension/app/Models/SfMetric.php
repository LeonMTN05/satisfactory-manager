<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models;

use Illuminate\Database\Eloquent\Model;

class SfMetric extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sfmanager_metrics';

    protected $guarded = ['id'];

    protected $casts = ['online' => 'boolean', 'tick_rate' => 'float'];
}
