<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SaveGameRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_CONSOLE;
    }

    public function rules(): array
    {
        // Satisfactory save names: letters, digits, space, dash and underscore.
        return ['name' => ['required', 'string', 'max:64', 'regex:/^[\\pL\\pN _-]+$/u']];
    }
}
