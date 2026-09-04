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

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Security\SealedPackage;
use VTInnovations\AccessPlus\Security\SealedPackageReader;

/**
 * Outbound half of the exchange: first activation and administrator refresh.
 *
 * Transport hardening: one fixed https destination, TLS peer + hostname
 * verification on, redirects disabled (a 30x must never move the check to
 * another host), bounded connect/total time, a response size cap and a media
 * type check before anything is parsed.
 *
 * Logging discipline: operation, generic result category, HTTP status, elapsed
 * milliseconds, applied version and the PUBLIC key id. Never the packet, the
 * body, the nonce, the key, a key fingerprint/length, the digest or a signature.
 *
 * Note on the egress switch: the bundle's "no external calls" setting governs AI
 * and content egress. Registration is a prerequisite of the product itself and
 * talks only to the fixed vendor host, so it is not routed through that switch —
 * this is stated in the administrator documentation instead of being hidden.
 */
final class ExchangeClient
{
    private const MAX_RESPONSE_BYTES = 262144;

    /** Beyond this clock difference the exchange is treated as untrustworthy. */
    private const MAX_CLOCK_SKEW = 86400;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ServiceEndpoints $endpoints,
        private readonly SealedPackageReader $reader,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PackageRejected
     */
    public function activate(string $licenseKey, string $domain): SealedPackage
    {
        return $this->exchange('activate', [
            'action' => 'activate',
            'project' => ServiceEndpoints::PROJECT,
            'project_slug' => ServiceEndpoints::PROJECT_SLUG,
            'product_id' => ServiceEndpoints::PRODUCT_ID,
            'license_key' => $licenseKey,
            'domain' => $domain,
        ]);
    }

    /**
     * @throws PackageRejected
     */
    public function refresh(string $licenseKey, string $domain, int $currentVersion): SealedPackage
    {
        return $this->exchange('refresh', [
            'action' => 'refresh',
            'project' => ServiceEndpoints::PROJECT,
            'project_slug' => ServiceEndpoints::PROJECT_SLUG,
            'product_id' => ServiceEndpoints::PRODUCT_ID,
            'license_key' => $licenseKey,
            'domain' => $domain,
            'current_license_version' => $currentVersion,
        ]);
    }

    /**
     * @param array<string, mixed> $packet
     *
     * @throws PackageRejected
     */
    private function exchange(string $operation, array $packet): SealedPackage
    {
        $url = $this->endpoints->verify();

        if (!$this->endpoints->isOwnDestination($url)) {
            throw new PackageRejected('destination_rejected');
        }

        $requestId = bin2hex(random_bytes(16));
        $packet['request_id'] = $requestId;
        $packet['timestamp'] = time();
        $packet['nonce'] = bin2hex(random_bytes(24));

        $body = json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!\is_string($body)) {
            throw new PackageRejected('request_encoding_failed');
        }

        $started = microtime(true);
        $status = 0;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => $body,
                'timeout' => 8,
                'max_duration' => 20,
                'max_redirects' => 0,
                'verify_peer' => true,
                'verify_host' => true,
            ]);

            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));
            $raw = $response->getContent(false);
        } catch (HttpExceptionInterface) {
            // Network error, TLS problem, timeout, 5xx: the previously valid
            // local state is untouched by definition — we never got here with a
            // replacement.
            $this->log($operation, 'transport_failed', $status, $started, null, null);

            throw new PackageRejected('transport_failed');
        }

        if ($status !== 200) {
            $this->log($operation, 'http_status_rejected', $status, $started, null, null);

            throw new PackageRejected('service_unavailable');
        }

        if (!str_starts_with($contentType, 'application/json')) {
            $this->log($operation, 'media_type_rejected', $status, $started, null, null);

            throw new PackageRejected('media_type_rejected');
        }

        if (\strlen($raw) > self::MAX_RESPONSE_BYTES) {
            $this->log($operation, 'response_size_rejected', $status, $started, null, null);

            throw new PackageRejected('response_size_rejected');
        }

        try {
            $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->log($operation, 'malformed_response', $status, $started, null, null);

            throw new PackageRejected('malformed_response');
        }

        if (!\is_array($payload)) {
            $this->log($operation, 'malformed_response', $status, $started, null, null);

            throw new PackageRejected('malformed_response');
        }

        if (!\is_string($payload['request_id'] ?? null) || !hash_equals($requestId, (string) $payload['request_id'])) {
            $this->log($operation, 'correlation_failed', $status, $started, null, null);

            throw new PackageRejected('correlation_failed');
        }

        $serverTime = $payload['server_time'] ?? null;
        if (\is_int($serverTime) && abs($serverTime - time()) > self::MAX_CLOCK_SKEW) {
            $this->log($operation, 'clock_skew_rejected', $status, $started, null, null);

            throw new PackageRejected('clock_skew_rejected');
        }

        // `valid` is the positive case; `revoked` and `expired` are authentic
        // negative states the client must still apply (a lease refresh is how an
        // installation that missed the push learns it has been withdrawn). Any
        // other status is a genuine denial.
        if (!\in_array($payload['status'] ?? null, ['valid', 'revoked', 'expired'], true)) {
            $this->log($operation, 'service_denied', $status, $started, null, null);

            throw new PackageRejected('service_denied');
        }

        try {
            $package = $this->reader->open($payload);
        } catch (PackageRejected $e) {
            $this->log($operation, $e->category(), $status, $started, null, null);

            throw $e;
        }

        $this->log($operation, 'ok', $status, $started, $package->version(), $package->keyId());

        return $package;
    }

    private function log(string $operation, string $result, int $status, float $started, ?int $version, ?string $keyId): void
    {
        $context = [
            'op' => $operation,
            'result' => $result,
            'http' => $status,
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        if ($version !== null) {
            $context['version'] = $version;
        }

        if ($keyId !== null && $keyId !== '') {
            $context['key_id'] = $keyId;
        }

        $this->logger->info('accessplus registration exchange', $context);
    }
}
