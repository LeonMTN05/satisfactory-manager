<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class NewGameRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_RESTART;
    }

    public function rules(): array
    {
        return [
            'session_name' => 'required|string|min:1|max:64',
            'starting_location' => 'required|string|in:Grass Fields,Rocky Desert,Northern Forest,Dune Desert',
            'skip_onboarding' => 'boolean',
        ];
    }
}
