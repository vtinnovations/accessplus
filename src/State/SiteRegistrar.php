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

use VTInnovations\AccessPlus\Exchange\ExchangeClient;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Security\SealedPackageReader;

/**
 * The three administrator operations, and the only writers of the authoritative
 * store besides the inbound callback.
 *
 * Every one of them ends in exactly one state transition:
 *   activate — unlicensed/invalid → authenticated package for this root;
 *   refresh  — authenticated package → newer authenticated package, or nothing
 *              at all if anything fails (the previous state is never touched
 *              before a replacement has passed every check);
 *   remove   — any state → unlicensed, and Contao's default behaviour is back on
 *              the next request for that root.
 *
 * Failures throw {@see PackageRejected} carrying an internal category. The UI
 * turns that into a generic message; the category itself never reaches the
 * browser as protocol detail.
 */
final class SiteRegistrar
{
    public function __construct(
        private readonly ExchangeClient $client,
        private readonly RegistrationStore $store,
        private readonly SealedPackageReader $reader,
        private readonly SiteStatusProvider $status,
        private readonly DomainInventory $domains,
        private readonly RootScope $rootScope,
        private readonly BackendSessionClaim $claim,
    ) {
    }

    /**
     * @throws PackageRejected
     */
    public function activate(int $rootId, string $licenseKey): SiteStatus
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            throw new PackageRejected('key_missing');
        }

        return $this->apply($rootId, static fn (ExchangeClient $client, string $domain): object => $client->activate($licenseKey, $domain), $licenseKey);
    }

    /**
     * Uses the stored key unless the administrator explicitly entered a
     * replacement one.
     *
     * @throws PackageRejected
     */
    public function refresh(int $rootId, string $replacementKey = ''): SiteStatus
    {
        $replacementKey = trim($replacementKey);
        $current = $this->status->forRoot($rootId);
        $key = $replacementKey !== '' ? $replacementKey : $current->key();

        if ($key === '') {
            throw new PackageRejected('key_missing');
        }

        // Report the version we actually hold, even when the stored record is
        // currently not acceptable (e.g. it predates the signed host set).
        $stored = $this->store->read($rootId);
        $version = max($current->version, (int) ($stored['envelope']['license_version'] ?? 0));

        return $this->apply($rootId, static fn (ExchangeClient $client, string $domain): object => $client->refresh($key, $domain, $version), $key);
    }

    /**
     * Returns the root to the unlicensed/default state. Only this bundle's own
     * private state is deleted; findings, drafts, settings and content stay.
     */
    public function remove(int $rootId): void
    {
        $this->store->remove($rootId);
        $this->status->forget($rootId);
        $this->claim->release($rootId);
    }

    /**
     * @param \Closure(ExchangeClient, string): object $call
     *
     * @throws PackageRejected
     */
    private function apply(int $rootId, \Closure $call, string $licenseKey): SiteStatus
    {
        if ($rootId <= 0 || $this->rootScope->root($rootId) === null) {
            throw new PackageRejected('scope_invalid');
        }

        $domain = $this->domains->verificationHost($rootId);

        if ($domain === null) {
            throw new PackageRejected('no_configured_domain');
        }

        /** @var \VTInnovations\AccessPlus\Security\SealedPackage $package */
        $package = $call($this->client, $domain);

        // The returned record must be for the key we actually sent.
        $returnedKey = $package->document['license_key'] ?? null;

        if (!\is_string($returnedKey) || !hash_equals($licenseKey, $returnedKey)) {
            throw new PackageRejected('key_mismatch');
        }

        // Full product policy, including exact host binding against the domain we
        // sent and against this root's configured domains.
        $evaluated = $this->status->evaluate($package->document, $rootId, $domain);

        if ($evaluated->state === SiteState::Invalid || $evaluated->state === SiteState::Unlicensed) {
            throw new PackageRejected($evaluated->reason);
        }

        // Rollback prevention, also on the administrator path.
        $current = $this->store->read($rootId);

        if ($current !== null && $package->version() < (int) ($current['envelope']['license_version'] ?? 0)) {
            throw new PackageRejected('version_rejected');
        }

        try {
            $this->store->commit(
                $rootId,
                $package->bytes,
                $package->envelope,
                fn (string $bytes, array $envelope) => $this->reader->reopen($bytes, $envelope),
            );
        } catch (\Throwable) {
            throw new PackageRejected('activation_failed');
        }

        $this->status->forget($rootId);

        return $this->status->forRoot($rootId);
    }
}
