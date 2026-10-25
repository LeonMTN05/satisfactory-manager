<?php
// Mounted at /api/client/extensions/{identifier}

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Controllers\ServerController;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Controllers\AutomationController;

Route::get('/ping', fn () => ['ok' => true, 'version' => '{version}']);

Route::get('/badges', [AutomationController::class, 'badges']);

Route::group([
    'prefix' => '/servers/{server}',
    'middleware' => [ServerSubject::class, AuthenticateServerAccess::class, ResourceBelongsToServer::class],
], function () {
    Route::get('/status', [ServerController::class, 'status']);
    Route::get('/connection', [ServerController::class, 'connection'])->middleware('throttle:20,1,sfmanager');
    Route::post('/new-game', [ServerController::class, 'newGame'])->middleware('throttle:20,1,sfmanager');
    Route::post('/save', [ServerController::class, 'save'])->middleware('throttle:20,1,sfmanager');

    Route::get('/sessions', [ServerController::class, 'sessions']);
    Route::post('/saves/load', [ServerController::class, 'load'])->middleware('throttle:20,1,sfmanager');
    Route::post('/saves/delete', [ServerController::class, 'deleteSave'])->middleware('throttle:20,1,sfmanager');
    Route::post('/sessions/delete', [ServerController::class, 'deleteSession'])->middleware('throttle:20,1,sfmanager');
    Route::post('/sessions/autoload', [ServerController::class, 'autoload'])->middleware('throttle:20,1,sfmanager');

    Route::get('/settings', [ServerController::class, 'settings']);
    Route::put('/settings/options', [ServerController::class, 'updateOptions'])->middleware('throttle:20,1,sfmanager');
    Route::put('/settings/advanced', [ServerController::class, 'updateAdvanced'])->middleware('throttle:20,1,sfmanager');
    Route::post('/settings/rename', [ServerController::class, 'rename'])->middleware('throttle:20,1,sfmanager');
    Route::post('/settings/client-password', [ServerController::class, 'clientPassword'])->middleware('throttle:5,1,sfmanager-auth');
    Route::post('/settings/admin-password', [ServerController::class, 'adminPassword'])->middleware('throttle:5,1,sfmanager-auth');

    Route::post('/command', [ServerController::class, 'command'])->middleware('throttle:30,1,sfmanager-cmd');
    Route::post('/power/restart', [ServerController::class, 'restart'])->middleware('throttle:5,1,sfmanager-power');
    Route::post('/power/stop', [ServerController::class, 'stop'])->middleware('throttle:5,1,sfmanager-power');

    Route::get('/automation', [AutomationController::class, 'show']);
    Route::put('/automation', [AutomationController::class, 'update'])->middleware('throttle:20,1,sfmanager');
    Route::get('/backups', [AutomationController::class, 'backups']);
    Route::post('/backups', [AutomationController::class, 'createBackup'])->middleware('throttle:5,1,sfmanager-backup');
    Route::post('/backups/{backup}/restore', [AutomationController::class, 'restoreBackup'])->whereNumber('backup')->middleware('throttle:5,1,sfmanager-backup');
    Route::delete('/backups/{backup}', [AutomationController::class, 'deleteBackup'])->whereNumber('backup');
    Route::get('/metrics', [AutomationController::class, 'metrics']);
    Route::post('/update', [AutomationController::class, 'runUpdate'])->middleware('throttle:5,1,sfmanager-power');
    Route::put('/branch', [AutomationController::class, 'branch'])->middleware('throttle:20,1,sfmanager');

    // Password-related actions are rate-limited against brute force.
    Route::post('/claim', [ServerController::class, 'claim'])->middleware('throttle:5,1,sfmanager-auth');
    Route::post('/login', [ServerController::class, 'login'])->middleware('throttle:5,1,sfmanager-auth');
    Route::delete('/login', [ServerController::class, 'logout']);
});
