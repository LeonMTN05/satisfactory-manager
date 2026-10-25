<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Controllers;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\SatisfactoryService;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\AutomationSettings;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\BackupService;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\GlobalSettings;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ClaimRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\LoginRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\LogoutRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\NewGameRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\SaveGameRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ViewStatusRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ListSavesRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\LoadSaveRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\DeleteSaveRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\DeleteSessionRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\AutoloadRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ReadSettingsRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\UpdateSettingsRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\RenameRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\ClientPasswordRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\AdminPasswordRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\CommandRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\RestartRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Requests\StopRequest;

class ServerController extends ClientApiController
{
    public function __construct(private SatisfactoryService $service, private DaemonPowerRepository $power)
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

    public function claim(ClaimRequest $request, Server $server): JsonResponse
    {
        $warnings = $this->service->claim(
            $server,
            $request->input('server_name'),
            $request->input('admin_password'),
            $request->input('client_password'),
            $request->input('new_game'),
        );

        Activity::event('server:sfmanager.claim')
            ->property('name', $request->input('server_name'))
            ->log();

        return new JsonResponse(['warnings' => $warnings]);
    }

    public function newGame(NewGameRequest $request, Server $server): Response
    {
        $this->service->newGame(
            $server,
            $request->input('session_name'),
            $request->input('starting_location'),
            $request->boolean('skip_onboarding'),
        );

        Activity::event('server:sfmanager.new-game')
            ->property('name', $request->input('session_name'))
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

    // ---- Savegames -----------------------------------------------------------

    public function sessions(ListSavesRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->sessions($server));
    }

    public function load(LoadSaveRequest $request, Server $server): Response
    {
        if (AutomationSettings::get($server)['backup']['before_load'] && app(GlobalSettings::class)->enabled('backups')) {
            app(BackupService::class)->create($server, 'before-load');
        }
        $this->service->load($server, $request->input('name'), $request->boolean('advanced'));
        Activity::event('server:sfmanager.load')->property('name', $request->input('name'))->log();

        return $this->returnNoContent();
    }

    public function deleteSave(DeleteSaveRequest $request, Server $server): Response
    {
        $this->service->deleteSave($server, $request->input('name'));
        Activity::event('server:sfmanager.delete-save')->property('name', $request->input('name'))->log();

        return $this->returnNoContent();
    }

    public function deleteSession(DeleteSessionRequest $request, Server $server): Response
    {
        $this->service->deleteSession($server, $request->input('session'));
        Activity::event('server:sfmanager.delete-session')->property('name', $request->input('session'))->log();

        return $this->returnNoContent();
    }

    public function autoload(AutoloadRequest $request, Server $server): Response
    {
        $this->service->setAutoload($server, $request->input('session'));
        Activity::event('server:sfmanager.autoload')->property('name', $request->input('session'))->log();

        return $this->returnNoContent();
    }

    // ---- Settings -------------------------------------------------------------

    public function settings(ReadSettingsRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->service->settings($server));
    }

    public function updateOptions(UpdateSettingsRequest $request, Server $server): Response
    {
        $settings = $request->input('settings');
        $this->service->applyOptions($server, $settings);
        Activity::event('server:sfmanager.options')->property('changes', $settings)->log();

        return $this->returnNoContent();
    }

    public function updateAdvanced(UpdateSettingsRequest $request, Server $server): Response
    {
        $settings = $request->input('settings');
        $this->service->applyAdvanced($server, $settings);
        Activity::event('server:sfmanager.advanced')->property('changes', $settings)->log();

        return $this->returnNoContent();
    }

    public function rename(RenameRequest $request, Server $server): Response
    {
        $this->service->rename($server, $request->input('server_name'));
        Activity::event('server:sfmanager.rename')->property('name', $request->input('server_name'))->log();

        return $this->returnNoContent();
    }

    public function clientPassword(ClientPasswordRequest $request, Server $server): Response
    {
        $this->service->setClientPassword($server, (string) $request->input('password', ''));
        Activity::event('server:sfmanager.client-password')->log();

        return $this->returnNoContent();
    }

    public function adminPassword(AdminPasswordRequest $request, Server $server): Response
    {
        $this->service->setAdminPassword($server, $request->input('password'));
        Activity::event('server:sfmanager.admin-password')->log();

        return $this->returnNoContent();
    }

    // ---- Console & power ------------------------------------------------------

    public function command(CommandRequest $request, Server $server): JsonResponse
    {
        $result = $this->service->command($server, $request->input('command'));
        Activity::event('server:sfmanager.command')->property('command', $request->input('command'))->log();

        return new JsonResponse($result);
    }

    public function restart(RestartRequest $request, Server $server): JsonResponse
    {
        return $this->powerAction($server, 'restart', $request->boolean('save', true));
    }

    public function stop(StopRequest $request, Server $server): JsonResponse
    {
        return $this->powerAction($server, 'stop', $request->boolean('save', true));
    }

    /** Optionally saves first, then lets Wings restart/stop the server the normal way. */
    private function powerAction(Server $server, string $action, bool $save): JsonResponse
    {
        $saveName = $save ? $this->service->saveBefore($server, 'before-' . $action) : null;
        $this->power->setServer($server)->send($action);

        Activity::event('server:sfmanager.' . $action)->property('save', $saveName)->log();

        return new JsonResponse(['save' => $saveName]);
    }
}
