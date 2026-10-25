<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Controllers;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\SatisfactoryService;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ClaimRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\LoginRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\LogoutRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\SaveGameRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ViewStatusRequest;

class ServerController extends ClientApiController
{
    public function __construct(private SatisfactoryService $service)
    {
        parent::__construct();
    }

    public function status(ViewStatusRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->status($server));
    }

    public function connection(ViewStatusRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->connection($server));
    }

    public function claim(ClaimRequest $request, Server $server): Response
    {
        $this->service->claim(
            $server,
            $request->input('server_name'),
            $request->input('admin_password'),
            $request->input('client_password'),
            $request->input('new_game'),
        );

        Activity::event('server:sfmanager.claim')
            ->property('name', $request->input('server_name'))
            ->log();

        return $this->returnNoContent();
    }

    public function login(LoginRequest $request, Server $server): JsonResponse
    {
        $privilege = $this->service->login($server, $request->input('password'));
        Activity::event('server:sfmanager.login')->log();

        return new JsonResponse(['privilege' => $privilege]);
    }

    public function logout(LogoutRequest $request, Server $server): Response
    {
        $this->service->forgetToken($server);
        Activity::event('server:sfmanager.logout')->log();

        return $this->returnNoContent();
    }

    public function save(SaveGameRequest $request, Server $server): Response
    {
        $this->service->save($server, $request->input('name'));

        Activity::event('server:sfmanager.save')
            ->property('name', $request->input('name'))
            ->log();

        return $this->returnNoContent();
    }
}
