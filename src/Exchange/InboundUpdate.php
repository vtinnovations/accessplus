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
use Symfony\Component\HttpFoundation\Request;
use VTInnovations\AccessPlus\Security\CallbackAuthenticator;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\State\DomainInventory;
use VTInnovations\AccessPlus\State\RegistrationStore;
use VTInnovations\AccessPlus\State\SiteState;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Inbound half of the exchange: the service pushes a complete new package.
 *
 * Order of business, all of it fail-closed:
 *   body size → media type → signature → header/body agreement → replay and
 *   idempotency → package authenticity → scope resolution → product policy →
 *   monotonic version → atomic activation → journal.
 *
 * The endpoint can only ever replace this bundle's own private state file for
 * one site root. It cannot write an arbitrary path, cannot touch source code and
 * derives nothing executable from the request.
 */
final class InboundUpdate
{
    private const MAX_BODY_BYTES = 262144;

    public function __construct(
        private readonly CallbackAuthenticator $authenticator,
        private readonly SealedPackageReader $reader,
        private readonly RegistrationStore $store,
        private readonly SiteStatusProvider $status,
        private readonly DomainInventory $domains,
        private readonly RequestJournal $journal,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(Request $request): array
    {
        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));

        if (!str_starts_with($contentType, 'application/json')) {
            return $this->fail(415, 'unsupported_media_type');
        }

        // Refuse on the declared length first, so an oversized body is not even
        // buffered, then re-check what actually arrived.
        if ((int) $request->headers->get('Content-Length', '0') > self::MAX_BODY_BYTES) {
            return $this->fail(413, 'body_too_large');
        }

        $raw = (string) $request->getContent();

        if (\strlen($raw) > self::MAX_BODY_BYTES) {
            return $this->fail(413, 'body_too_large');
        }

        try {
            $meta = $this->authenticator->authenticate($request, $raw, ServiceEndpoints::CALLBACK_PATH);
        } catch (PackageRejected $e) {
            return $this->fail(401, $e->category());
        }

        try {
            $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->fail(400, 'malformed_body');
        }

        if (!\is_array($body)) {
            return $this->fail(400, 'malformed_body');
        }

        // The signed headers and the duplicated body metadata must agree exactly.
        if (!\is_string($body['request_id'] ?? null) || !hash_equals($meta['request_id'], (string) $body['request_id'])
            || !\is_string($body['nonce'] ?? null) || !hash_equals($meta['nonce'], (string) $body['nonce'])
            || (int) ($body['timestamp'] ?? 0) !== $meta['timestamp']
        ) {
            return $this->fail(403, 'callback_metadata_mismatch');
        }

        if (($body['action'] ?? null) !== 'license_update'
            || ($body['project'] ?? null) !== ServiceEndpoints::PROJECT
            || ($body['project_slug'] ?? null) !== ServiceEndpoints::PROJECT_SLUG
            || ($body['product_id'] ?? null) !== ServiceEndpoints::PRODUCT_ID
        ) {
            return $this->fail(403, 'callback_product_mismatch');
        }

        $bodyDigest = RequestJournal::digest($raw);
        $nonceDigest = RequestJournal::digest($meta['nonce']);
        $previous = $this->journal->find($meta['request_id']);

        if ($previous !== null) {
            if (hash_equals($previous['body'], $bodyDigest) && $previous['result'] === 'updated') {
                $this->log('already_processed', 200, $previous['version'], $meta['key_id']);

                return [
                    'status' => 200,
                    'body' => [
                        'status' => 'already_processed',
                        'request_id' => $meta['request_id'],
                        'license_version' => $previous['version'],
                    ],
                ];
            }

            // Same identifier, different authenticated content: not a retry.
            return $this->fail(403, 'callback_request_id_conflict');
        }

        if ($this->journal->nonceSeen($nonceDigest)) {
            return $this->fail(403, 'callback_nonce_replayed');
        }

        try {
            $package = $this->reader->open($body);
        } catch (PackageRejected $e) {
            return $this->fail(403, $e->category());
        }

        $domain = $this->domains->normalize(\is_string($body['domain'] ?? null) ? (string) $body['domain'] : null);

        if ($domain === null) {
            return $this->fail(403, 'callback_domain_invalid');
        }

        $rootId = $this->domains->rootForHost($domain);

        if ($rootId <= 0) {
            return $this->fail(403, 'callback_scope_unknown');
        }

        $evaluated = $this->status->evaluate($package->document, $rootId, $domain);

        if ($evaluated->state === SiteState::Invalid || $evaluated->state === SiteState::Unlicensed) {
            return $this->fail(403, $evaluated->reason);
        }

        // Rollback prevention: an older or equal version can never replace the
        // current authenticated state.
        $current = $this->store->read($rootId);

        if ($current !== null) {
            $currentVersion = (int) ($current['envelope']['license_version'] ?? 0);

            if ($package->version() <= $currentVersion) {
                $this->journal->record($meta['request_id'], $nonceDigest, $bodyDigest, $currentVersion, 'version_rejected');

                return $this->fail(409, 'version_rejected');
            }
        }

        try {
            $this->store->commit(
                $rootId,
                $package->bytes,
                $package->envelope,
                fn (string $bytes, array $envelope) => $this->reader->reopen($bytes, $envelope),
            );
        } catch (\Throwable) {
            return $this->fail(500, 'activation_failed');
        }

        $this->status->forget($rootId);
        $this->journal->record($meta['request_id'], $nonceDigest, $bodyDigest, $package->version(), 'updated');
        $this->log('updated', 200, $package->version(), $meta['key_id']);

        return [
            'status' => 200,
            'body' => [
                'status' => 'updated',
                'request_id' => $meta['request_id'],
                'license_version' => $package->version(),
            ],
        ];
    }

    /**
     * Generic outward answer; the real category stays in the operational log.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function fail(int $status, string $category): array
    {
        $this->log($category, $status, null, null);

        return ['status' => $status, 'body' => ['status' => 'rejected']];
    }

    private function log(string $result, int $status, ?int $version, ?string $keyId): void
    {
        $context = ['op' => 'callback', 'result' => $result, 'http' => $status];

        if ($version !== null) {
            $context['version'] = $version;
        }

        if ($keyId !== null && $keyId !== '') {
            $context['key_id'] = $keyId;
        }

        $this->logger->info('accessplus registration callback', $context);
    }
}
