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

namespace VTInnovations\AccessPlus\State;

use VTInnovations\AccessPlus\Exchange\ServiceEndpoints;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Security\SealedPackageReader;

/**
 * Turns stored state into the immutable per-root answer the feature gates use.
 *
 * Pro-only product: the accepted package allowlist has exactly one member. There
 * is no trial, no anonymous free mode, no grace window, no "installed therefore
 * enabled" path and no local fallback — a root without an authentic, in-window,
 * domain-matched Pro record simply gets Contao's default behaviour back.
 *
 * The stored record is re-verified cryptographically on EVERY evaluation (the
 * result is cached per request only), so editing the state file by hand fails at
 * the signature and the exact-byte digest rather than at a cached boolean.
 */
final class SiteStatusProvider
{
    /** Pro-only: the complete accepted package allowlist. */
    private const PACKAGES = ['pro'];

    private const SCHEMA_VERSION = 2;

    /** @var array<int, SiteStatus> per-request memo, dropped on any state change */
    private array $memo = [];

    /** Set once an active root was actually evaluated during this invocation. */
    private ?string $activeDomain = null;

    public function __construct(
        private readonly RegistrationStore $store,
        private readonly SealedPackageReader $reader,
        private readonly DomainInventory $domains,
        private readonly RootScope $rootScope,
    ) {
    }

    public function forRoot(int $rootId): SiteStatus
    {
        if (isset($this->memo[$rootId])) {
            return $this->memo[$rootId];
        }

        return $this->memo[$rootId] = $this->resolve($rootId);
    }

    /**
     * Convenience for the gates: "may this root use the bundle at all?".
     */
    public function isActive(int $rootId): bool
    {
        return $rootId > 0 && $this->forRoot($rootId)->isActive();
    }

    /**
     * @return list<int>
     */
    public function activeRootIds(): array
    {
        $out = [];

        foreach ($this->rootScope->roots() as $root) {
            if ($this->forRoot($root['id'])->isActive()) {
                $out[] = $root['id'];
            }
        }

        return $out;
    }

    public function hasAnyActive(): bool
    {
        return $this->activeRootIds() !== [];
    }

    /**
     * Drops the memo for one root after an activation/refresh/removal so the very
     * next read reflects the new authoritative state.
     */
    public function forget(int $rootId): void
    {
        unset($this->memo[$rootId]);
    }

    /**
     * Matched domain of an active root evaluated during this invocation, used by
     * the deferred invocation signal. Null when nothing licensed was touched.
     */
    public function invocationDomain(): ?string
    {
        return $this->activeDomain;
    }

    /**
     * Applies the product policy to an already AUTHENTICATED document.
     *
     * $expectedDomain is passed during activation/refresh/callback handling: the
     * signed `license_domain` must then equal the host we actually sent, which
     * closes the "signed for a different host, replayed here" hole.
     *
     * @param array<string, mixed> $document
     */
    public function evaluate(array $document, int $rootId, ?string $expectedDomain = null): SiteStatus
    {
        $configured = $this->domains->forRoot($rootId);
        $key = \is_string($document['license_key'] ?? null) ? (string) $document['license_key'] : '';

        $reject = static fn (string $reason): SiteStatus => SiteStatus::rejected(
            $rootId,
            $reason,
            $configured,
            $key,
            (int) ($document['license_version'] ?? 0),
        );

        if ((int) ($document['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            return $reject('schema_unsupported');
        }

        if (($document['project'] ?? null) !== ServiceEndpoints::PROJECT
            || ($document['project_slug'] ?? null) !== ServiceEndpoints::PROJECT_SLUG
        ) {
            return $reject('foreign_product');
        }

        if ($key === '') {
            return $reject('key_missing');
        }

        if (($document['validation_status'] ?? null) !== 'valid') {
            return $reject('status_not_valid');
        }

        $version = $document['license_version'] ?? null;
        if (!\is_int($version) || $version < 1) {
            return $reject('version_invalid');
        }

        // Pro only. A free/trial package is not degraded to "some access" here —
        // it is simply not a package this product accepts.
        $package = \is_string($document['license_package'] ?? null) ? strtolower((string) $document['license_package']) : '';
        if (!\in_array($package, self::PACKAGES, true)) {
            return $reject('package_not_permitted');
        }

        // A pre-upgrade record without the signed host set is rollback material
        // only: never invent the fields locally, require a real refresh.
        if (!\array_key_exists('license_domains', $document) || !\array_key_exists('license_max_domains', $document)) {
            return $reject('refresh_required');
        }

        $signed = $this->domains->acceptSignedSet($document['license_domains']);
        if ($signed === null) {
            return $reject('domain_set_invalid');
        }

        $maxDomains = $document['license_max_domains'] ?? null;
        if (!\is_int($maxDomains) || $maxDomains < 1) {
            return $reject('allowance_invalid');
        }

        $operationDomain = \is_string($document['license_domain'] ?? null) ? (string) $document['license_domain'] : '';
        if ($operationDomain === ''
            || $this->domains->normalize($operationDomain) !== $operationDomain
            || !\in_array($operationDomain, $signed, true)
        ) {
            return $reject('domain_binding_invalid');
        }

        if ($expectedDomain !== null && !hash_equals($expectedDomain, $operationDomain)) {
            return $reject('domain_mismatch');
        }

        // Exact set intersection with the hosts Contao has configured on this
        // root. No suffix, apex/www, parent, child or wildcard equivalence.
        $intersection = $this->domains->intersect($rootId, $signed);
        if ($intersection === []) {
            return $reject('domain_not_configured');
        }

        $current = $this->domains->currentHost();
        $matched = $current !== null && \in_array($current, $intersection, true) ? $current : $intersection[0];

        $lifetime = $document['license_lifetime'] ?? null;
        if (!\is_bool($lifetime)) {
            return $reject('lifetime_invalid');
        }

        $issuedAt = \is_int($document['license_issued_at'] ?? null) ? (int) $document['license_issued_at'] : null;
        $startsAt = \is_int($document['license_starts_at'] ?? null) ? (int) $document['license_starts_at'] : null;
        $verifiedAt = \is_int($document['license_verified_at'] ?? null) ? (int) $document['license_verified_at'] : null;
        $expiresAt = $document['license_expires_at'] ?? null;

        if ($startsAt === null) {
            return $reject('dates_invalid');
        }

        if ($lifetime) {
            if ($expiresAt !== null) {
                return $reject('lifetime_expiry_conflict');
            }
        } else {
            if (!\is_int($expiresAt) || $expiresAt <= $startsAt) {
                return $reject('expiry_invalid');
            }
        }

        $now = time();
        $state = SiteState::Active;
        $reason = '';

        if ($now < $startsAt) {
            return $reject('not_yet_valid');
        }

        if (!$lifetime && \is_int($expiresAt) && $now >= $expiresAt) {
            // Pro-only: an expired record has no free fallback whatsoever.
            $state = SiteState::Expired;
            $reason = 'expired';
        }

        return SiteStatus::of(
            $rootId,
            $state,
            $reason,
            $package,
            $version,
            $lifetime,
            $startsAt,
            \is_int($expiresAt) ? $expiresAt : null,
            $issuedAt,
            $verifiedAt,
            $matched,
            $signed,
            $configured,
            $maxDomains,
            $key,
        );
    }

    private function resolve(int $rootId): SiteStatus
    {
        if ($rootId <= 0) {
            return SiteStatus::unlicensed($rootId, [], 'no_scope');
        }

        $stored = $this->store->read($rootId);

        if ($stored === null) {
            return SiteStatus::unlicensed($rootId, $this->domains->forRoot($rootId));
        }

        try {
            $package = $this->reader->reopen($stored['bytes'], $stored['envelope']);
        } catch (PackageRejected $e) {
            return SiteStatus::rejected($rootId, $e->category(), $this->domains->forRoot($rootId));
        } catch (\Throwable) {
            return SiteStatus::rejected($rootId, 'state_unreadable', $this->domains->forRoot($rootId));
        }

        $status = $this->evaluate($package->document, $rootId);

        // Remember a domain for the deferred invocation signal. The host the
        // current request actually came in on wins, so that a survey of all roots
        // (cron, backend hub) cannot make the signal report an unrelated domain.
        if ($status->isActive() && $status->matchedDomain !== null
            && ($this->activeDomain === null || $status->matchedDomain === $this->domains->currentHost())
        ) {
            $this->activeDomain = $status->matchedDomain;
        }

        return $status;
    }
}
