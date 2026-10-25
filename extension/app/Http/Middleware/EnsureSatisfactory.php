<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\GlobalSettings;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\SatisfactoryApiException;

/**
 * Only servers with a Satisfactory egg (see admin page) may use the extension's server endpoints.
 */
class EnsureSatisfactory
{
    public function __construct(private GlobalSettings $global)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $server = $request->route()->parameter('server');

        if ($server instanceof Server && !$this->global->isSatisfactory($server)) {
            throw new SatisfactoryApiException('sfm_not_satisfactory', 'This server does not use a Satisfactory egg.', 404);
        }

        return $next($request);
    }
}
