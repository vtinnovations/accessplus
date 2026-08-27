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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Exact-host identity for a site root (Modell 2).
 *
 * The rule is deliberately strict: `example.com`, `www.example.com`,
 * `shop.example.com` and `admin.shop.example.com` are FOUR different identities.
 * There is no apex/www folding, no registrable-domain (eTLD+1) reduction, no
 * suffix match, no wildcard and no alias/CNAME following anywhere in this class.
 * {@see RootScope::rootIdForHost()} keeps its historic tolerant matching for
 * finding-scope bookkeeping; nothing here uses it.
 *
 * Normalization changes REPRESENTATION only — lowercase, one trailing dot, an
 * explicit port, IDN to Punycode. It never changes which host is meant.
 *
 * The configured inventory comes from Contao's own root-page configuration
 * (`tl_page.dns`), never from a request header, so a spoofed Host cannot select
 * the licensed identity. The current request host is used only when it is
 * already a member of that configured inventory.
 */
final class DomainInventory
{
    public function __construct(
        private readonly RootScope $rootScope,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Trusted, configured hosts of one site root. Contao stores exactly one
     * domain per root page; an empty value means "no domain configured", which
     * is not licensable and returns an empty inventory.
     *
     * @return list<string>
     */
    public function forRoot(int $rootId): array
    {
        if ($rootId <= 0) {
            return [];
        }

        $root = $this->rootScope->root($rootId);

        if ($root === null) {
            return [];
        }

        $host = $this->normalize($root['dns']);

        return $host === null ? [] : [$host];
    }

    /**
     * Host of the current request, normalized. Symfony resolves this through the
     * configured trusted-proxy/trusted-host settings of the installation, so an
     * untrusted X-Forwarded-Host cannot reach us here. Returns null outside a
     * request (CLI, cron, queue).
     */
    public function currentHost(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return null;
        }

        try {
            return $this->normalize($request->getHost());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The host to send for an activation/refresh of this root: the current
     * request host when it is part of the configured inventory, otherwise the
     * root's configured domain. Null when the root has no domain at all.
     */
    public function verificationHost(int $rootId): ?string
    {
        $inventory = $this->forRoot($rootId);

        if ($inventory === []) {
            return null;
        }

        $current = $this->currentHost();

        if ($current !== null && \in_array($current, $inventory, true)) {
            return $current;
        }

        return $inventory[0];
    }

    /**
     * Exact intersection between the configured inventory of a root and a signed
     * host set. Sorted and de-duplicated so the chosen host is deterministic
     * across requests, CLI runs and background work.
     *
     * @param list<string> $signed
     *
     * @return list<string>
     */
    public function intersect(int $rootId, array $signed): array
    {
        $inventory = $this->forRoot($rootId);
        $out = [];

        foreach ($signed as $host) {
            if (\is_string($host) && \in_array($host, $inventory, true)) {
                $out[$host] = $host;
            }
        }

        $out = array_values($out);
        sort($out, SORT_STRING);

        return $out;
    }

    /**
     * The site root that has $host configured, by EXACT match. 0 when no root
     * claims it. Used to route an inbound service callback to its scope.
     */
    public function rootForHost(string $host): int
    {
        $needle = $this->normalize($host);

        if ($needle === null) {
            return 0;
        }

        foreach ($this->rootScope->roots() as $root) {
            if ($this->normalize($root['dns']) === $needle) {
                return $root['id'];
            }
        }

        return 0;
    }

    /**
     * Canonical representation of a hostname, or null when the value is not a
     * hostname we may bind to.
     *
     * Rejected: empty, wildcard, underscore, credentials/path/scheme leftovers,
     * over-long labels and bare IP literals (an IP is not a licensable identity
     * for this product).
     */
    public function normalize(?string $host): ?string
    {
        $host = trim((string) $host);

        if ($host === '') {
            return null;
        }

        // Strip an accidental scheme/path/credentials pasted into configuration.
        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);
            $host = \is_string($parsed) ? $parsed : '';
        }

        $host = trim($host, " \t\n\r\0\x0B");

        if ($host === '' || str_contains($host, '/') || str_contains($host, '@') || str_contains($host, ' ')) {
            return null;
        }

        // One explicit port, removed. IPv6 literals are rejected below anyway.
        if (preg_match('/^(.+):\d{1,5}$/', $host, $m) === 1) {
            $host = $m[1];
        }

        // Exactly one trailing dot may be dropped (FQDN notation).
        if (str_ends_with($host, '.')) {
            $host = substr($host, 0, -1);
        }

        if ($host === '' || str_contains($host, '*')) {
            return null;
        }

        // IDN → Punycode, consistently, before lowercasing ASCII.
        if (preg_match('/[^\x20-\x7E]/', $host) === 1) {
            if (!\function_exists('idn_to_ascii')) {
                return null;
            }

            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (!\is_string($ascii) || $ascii === '') {
                return null;
            }

            $host = $ascii;
        }

        $host = strtolower($host);

        // Bare IP literals are not bindable identities.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_contains($host, ':')) {
            return null;
        }

        if (\strlen($host) > 253 || preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            return null;
        }

        return $host;
    }

    /**
     * Validates a SIGNED host set exactly as received: non-empty, unique, already
     * sorted, canonical and wildcard-free. The list is never re-sorted or
     * repaired locally — doing so before signature verification would let a
     * mangled list pass as authentic.
     *
     * @param mixed $value
     *
     * @return list<string>|null null when the set is not acceptable
     */
    public function acceptSignedSet(mixed $value): ?array
    {
        if (!\is_array($value) || $value === [] || !array_is_list($value)) {
            return null;
        }

        $out = [];
        $previous = null;

        foreach ($value as $entry) {
            if (!\is_string($entry) || $this->normalize($entry) !== $entry) {
                return null;
            }

            if ($previous !== null && strcmp($entry, $previous) <= 0) {
                return null; // unsorted or duplicated
            }

            $previous = $entry;
            $out[] = $entry;
        }

        return $out;
    }
}
