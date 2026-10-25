<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Error from the Satisfactory HTTPS API or from the extension itself.
 * `errorCode` is passed to the frontend so it can show a translated message.
 */
class SatisfactoryApiException extends DisplayException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private int $status = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }

    public static function unreachable(?\Throwable $previous = null): self
    {
        return new self('sfm_unreachable', 'The Satisfactory server is not reachable. Is it running?', 502, $previous);
    }

    public static function loginRequired(): self
    {
        return new self('sfm_login_required', 'Admin login required for this action.', 403);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'code' => $this->errorCode,
                'status' => (string) $this->status,
                'detail' => $this->getMessage(),
            ]],
        ], $this->status);
    }
}
