<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class RestartRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_RESTART;
    }

    public function rules(): array
    {
        return [
            'save' => 'boolean',
        ];
    }
}
