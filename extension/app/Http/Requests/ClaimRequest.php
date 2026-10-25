<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ClaimRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'server_name' => 'required|string|min:1|max:64',
            'admin_password' => 'required|string|min:4|max:255',
            'client_password' => 'nullable|string|max:255',
            'new_game' => 'nullable|array',
            'new_game.session_name' => 'required_with:new_game|string|min:1|max:64',
            'new_game.starting_location' => 'required_with:new_game|string|in:Grass Fields,Rocky Desert,Northern Forest,Dune Desert',
            'new_game.skip_onboarding' => 'boolean',
        ];
    }
}
