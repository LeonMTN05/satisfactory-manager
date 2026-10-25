<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Repositories\Wings\DaemonRepository;

/**
 * Small Wings helper for calls the panel has no repository method for.
 */
class Wings extends DaemonRepository
{
    /** State ("running", "offline", "starting", "stopping") and uptime in seconds, or null if Wings is unreachable. */
    public function state(): ?array
    {
        try {
            $data = json_decode((string) $this->getHttpClient()->get(sprintf('/api/servers/%s', $this->server->uuid))->getBody(), true);
        } catch (TransferException) {
            return null;
        }

        return [
            'state' => $data['state'] ?? 'offline',
            'uptime' => (int) floor(((int) ($data['utilization']['uptime'] ?? 0)) / 1000),
        ];
    }

    /** Last lines of the console log, without color codes. */
    public function logs(int $lines = 30): array
    {
        try {
            $data = json_decode((string) $this->getHttpClient()->get(
                sprintf('/api/servers/%s/logs', $this->server->uuid),
                ['query' => ['size' => $lines]]
            )->getBody(), true);
        } catch (TransferException) {
            return [];
        }

        return array_map(fn ($l) => preg_replace('/\x1b\[[0-9;]*m/', '', (string) $l), $data['data'] ?? []);
    }
}
