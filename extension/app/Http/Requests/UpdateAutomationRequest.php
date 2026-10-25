<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateAutomationRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'backup.interval_hours' => 'required|integer|in:0,1,2,3,6,12,24',
            'backup.keep' => 'required|integer|min:1|max:100',
            'backup.before_restart' => 'required|boolean',
            'backup.before_update' => 'required|boolean',
            'backup.before_load' => 'required|boolean',
            'restart.times' => 'present|array|max:6',
            'restart.times.*' => ['string', 'regex:/^([01]\\d|2[0-3]):[0-5]\\d$/'],
            'restart.only_empty' => 'required|boolean',
            'watchdog.enabled' => 'required|boolean',
            'watchdog.minutes' => 'required|integer|min:1|max:30',
        ];
    }
}
