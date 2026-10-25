<?php
// Mounted at /api/client/extensions/{identifier}

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Controllers\ServerController;

Route::get('/ping', fn () => ['ok' => true, 'version' => '{version}']);

Route::group([
    'prefix' => '/servers/{server}',
    'middleware' => [ServerSubject::class, AuthenticateServerAccess::class, ResourceBelongsToServer::class],
], function () {
    Route::get('/status', [ServerController::class, 'status']);
    Route::get('/connection', [ServerController::class, 'connection'])->middleware('throttle:20,1,sfmanager');
    Route::post('/save', [ServerController::class, 'save'])->middleware('throttle:20,1,sfmanager');

    // Password-related actions are rate-limited against brute force.
    Route::post('/claim', [ServerController::class, 'claim'])->middleware('throttle:5,1,sfmanager-auth');
    Route::post('/login', [ServerController::class, 'login'])->middleware('throttle:5,1,sfmanager-auth');
    Route::delete('/login', [ServerController::class, 'logout']);
});
