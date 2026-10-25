<?php

use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\TickService;

foreach (app(TickService::class)->run() as $line) {
    $this->line($line);
}
