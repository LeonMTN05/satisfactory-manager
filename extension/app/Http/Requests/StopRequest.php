<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StopRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_STOP;
    }

    public function rules(): array
    {
        return [
            'save' => 'boolean',
        ];
    }
}
