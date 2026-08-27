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

namespace VTInnovations\AccessPlus\Tests\Support;

use VTInnovations\AccessPlus\Security\CanonicalForm;
use VTInnovations\AccessPlus\Security\TrustAnchors;

/**
 * Builds signed packages for the tests with a throw-away key pair, and hands out
 * a matching {@see TrustAnchors} ring.
 *
 * This exercises the production verification path end to end without ever
 * contacting the vendor. It does NOT replace a real cross-system vector: drop a
 * genuine signed response into tests/Fixtures/vendor-response.json to have
 * VendorVectorTest verify the pinned production key as well.
 */
final class TestPackageFactory
{
    public readonly string $publicKey;

    private readonly string $secretKey;

    public function __construct()
    {
        $pair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($pair);
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
    }

    public function anchors(string $keyId = 'test-key', ?int $from = null, ?int $until = null): TrustAnchors
    {
        return new TrustAnchors([[
            'id' => $keyId,
            'algorithm' => TrustAnchors::ALGORITHM_ED25519,
            'key' => $this->publicKey,
            'purposes' => [TrustAnchors::PURPOSE_RECORD, TrustAnchors::PURPOSE_ENVELOPE, TrustAnchors::PURPOSE_REQUEST],
            'from' => $from ?? 0,
            'until' => $until,
        ]]);
    }

    public function sign(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secretKey));
    }

    /**
     * A complete, valid Pro document for one host.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public function document(array $overrides = []): array
    {
        $now = time();

        return array_replace([
            'schema_version' => 2,
            'project' => 'AccessPlus',
            'project_slug' => 'accessplus',
            'license_key' => 'AP-TEST-0000-1111',
            'license_domain' => 'example.com',
            'license_domains' => ['example.com', 'staging.example.com'],
            'license_max_domains' => 3,
            'license_package' => 'pro',
            'license_features' => [],
            'license_version' => 7,
            'license_issued_at' => $now - 86400,
            'license_starts_at' => $now - 86400,
            'license_expires_at' => $now + 31536000,
            'license_lifetime' => false,
            'license_verified_at' => $now,
            'free_available' => false,
            'validation_status' => 'valid',
        ], $overrides);
    }

    /**
     * Exact bytes of a signed document.
     *
     * @param array<string, mixed> $document
     */
    public function bytes(array $document): string
    {
        unset($document['signature']);
        $document['signature'] = $this->sign(CanonicalForm::document($document));

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::assertString($json);

        return $json;
    }

    /**
     * Signed integrity envelope for exact bytes.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public function envelope(string $bytes, int $version = 7, string $keyId = 'test-key', array $overrides = []): array
    {
        $envelope = array_replace([
            'project' => 'AccessPlus',
            'project_slug' => 'accessplus',
            'license_version' => $version,
            'license_md5' => md5($bytes),
            'generated_at' => time(),
            'key_id' => $keyId,
            'signature_algorithm' => TrustAnchors::ALGORITHM_ED25519,
        ], $overrides);

        unset($envelope['signature']);
        $envelope['signature'] = $this->sign(CanonicalForm::document($envelope));

        return $envelope;
    }

    /**
     * A complete successful response body.
     *
     * @param array<string, mixed> $document
     *
     * @return array{status: string, request_id: string, server_time: int, license_payload_b64: string, integrity: array<string, mixed>}
     */
    public function response(array $document, string $requestId = 'req-1'): array
    {
        $bytes = $this->bytes($document);

        return [
            'status' => 'valid',
            'request_id' => $requestId,
            'server_time' => time(),
            'license_payload_b64' => base64_encode($bytes),
            'integrity' => $this->envelope($bytes, (int) ($document['license_version'] ?? 7)),
        ];
    }

    private static function assertString(mixed $value): void
    {
        if (!\is_string($value)) {
            throw new \RuntimeException('Could not encode the test document.');
        }
    }
}
