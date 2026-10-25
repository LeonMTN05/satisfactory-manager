<?php

use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\GlobalSettings;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\MetricsService;

$days = (int) app(GlobalSettings::class)->get('retention_days');
$deleted = app(MetricsService::class)->prune($days);
$this->info("Removed $deleted metric rows older than $days days.");
