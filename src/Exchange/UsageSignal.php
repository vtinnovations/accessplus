<?php

declare(strict_types=1);

/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTInnovations\AccessPlus\Exchange;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The two documented signals, both server-to-server, both fire-and-forget.
 *
 *  - invocation:  {"project": "...", "domain": "..."} — at most once per relevant
 *    application invocation. Never carries the key.
 *  - module entry: {"domain": "...", "key": "..."} — exactly once per authenticated
 *    backend session, project and site root, when the licence section of that
 *    root is actually opened.
 *
 * The payloads are built here from an explicit allowlist, so no caller can widen
 * them into general telemetry. Nothing is logged, no response body is read, no
 * redirect is followed and a failure is silent: neither signal may influence
 * entitlement, rendering or the administrator's work.
 *
 * The module-entry event is the ONLY place the full key ever leaves the server,
 * and it goes exclusively to the fixed vendor host over TLS.
 */
final class UsageSignal
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ServiceEndpoints $endpoints,
    ) {
    }

    public function invocation(string $domain): void
    {
        if ($domain === '') {
            return;
        }

        $this->post(['project' => ServiceEndpoints::PROJECT, 'domain' => $domain]);
    }

    public function moduleEntry(string $domain, string $licenseKey): void
    {
        if ($domain === '' || $licenseKey === '') {
            return;
        }

        $this->post(['domain' => $domain, 'key' => $licenseKey]);
    }

    /**
     * @param array<string, string> $payload
     */
    private function post(array $payload): void
    {
        $url = $this->endpoints->signal();

        if (!$this->endpoints->isOwnDestination($url)) {
            return;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!\is_string($body)) {
            return;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body,
                'timeout' => 3,
                'max_duration' => 5,
                'max_redirects' => 0,
                'verify_peer' => true,
                'verify_host' => true,
            ]);

            // Symfony's client is lazy; touching the status code performs the
            // request. The body is deliberately never read.
            $response->getStatusCode();
        } catch (\Throwable) {
            // Silent by contract.
        }
    }
}
