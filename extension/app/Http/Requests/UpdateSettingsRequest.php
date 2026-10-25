<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateSettingsRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array|min:1',
            'settings.*' => 'present|string|max:128',
        ];
    }
}
