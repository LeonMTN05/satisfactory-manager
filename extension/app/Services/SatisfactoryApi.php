<?php

namespace Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin client for the Satisfactory Dedicated Server HTTPS API (/api/v1).
 * The server uses a self-signed certificate, so TLS verification is off.
 */
class SatisfactoryApi
{
    private Client $http;

    public function __construct(
        private string $host,
        private int $port,
        private ?string $token = null,
        float $timeout = 5.0,
    ) {
        $h = str_contains($host, ':') ? "[{$host}]" : $host;
        $this->http = new Client([
            'base_uri' => "https://{$h}:{$port}",
            'verify' => false,
            'timeout' => $timeout,
            'connect_timeout' => 3,
            'http_errors' => false,
        ]);
    }

    public function withToken(?string $token): self
    {
        $clone = clone $this;
        $clone->token = $token;

        return $clone;
    }

    /**
     * Calls an API function and returns its `data` object.
     *
     * @throws SatisfactoryApiException
     */
    public function call(string $function, array $data = [], ?float $timeout = null): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        try {
            $res = $this->http->post('/api/v1', [
                'headers' => $headers,
                'json' => ['function' => $function, 'data' => (object) $data],
            ] + ($timeout ? ['timeout' => $timeout] : []));
        } catch (GuzzleException $e) {
            throw SatisfactoryApiException::unreachable($e);
        }

        $status = $res->getStatusCode();
        if ($status === 204) {
            return [];
        }

        $body = json_decode((string) $res->getBody(), true) ?? [];
        // Note: the server sometimes reports errors with HTTP 200.
        if ($status >= 200 && $status < 300 && !isset($body['errorCode'])) {
            return $body['data'] ?? [];
        }

        throw new SatisfactoryApiException(
            $body['errorCode'] ?? 'sfm_http_' . $status,
            $body['errorMessage'] ?? "Satisfactory API returned HTTP {$status}.",
            in_array($status, [401, 403]) || in_array($body['errorCode'] ?? '', ['wrong_password', 'invalid_token', 'insufficient_scope'], true) ? 403 : 400,
        );
    }

    /** Returns the privilege level encoded in a token ("Administrator", "Client", ...). */
    public static function privilegeOf(string $token): ?string
    {
        $payload = json_decode(base64_decode(explode('.', $token)[0] ?? '') ?: '', true);

        return $payload['pl'] ?? null;
    }
}
