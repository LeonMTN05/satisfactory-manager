<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class LoginRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return ['password' => 'required|string|max:255'];
    }
}
