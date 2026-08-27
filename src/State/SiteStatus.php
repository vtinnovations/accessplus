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

/**
 * Immutable result of evaluating one site root.
 *
 * Shared as INPUT by the feature gates; it is not itself a switch. There is no
 * setter, no "force valid" flag and no mutable boolean that could be flipped to
 * open every gate at once — every protected boundary asks for its own root and
 * makes its own decision.
 *
 * The full key is deliberately not part of any array/render helper. It leaves
 * this object only through {@see key()}, which exists for exactly two callers:
 * the refresh request and the once-per-session module-entry signal. It must
 * never reach a template, a log, a diagnostic or the browser.
 */
final class SiteStatus
{
    /**
     * @param list<string> $signedDomains    exact host set as signed by the service
     * @param list<string> $configuredDomains exact hosts configured on this root
     */
    private function __construct(
        public readonly int $rootId,
        public readonly SiteState $state,
        public readonly string $reason,
        public readonly string $package,
        public readonly int $version,
        public readonly bool $lifetime,
        public readonly ?int $startsAt,
        public readonly ?int $expiresAt,
        public readonly ?int $issuedAt,
        public readonly ?int $verifiedAt,
        public readonly ?string $matchedDomain,
        public readonly array $signedDomains,
        public readonly array $configuredDomains,
        public readonly int $maxDomains,
        private readonly string $licenseKey,
    ) {
    }

    /**
     * No stored state at all for this root.
     */
    public static function unlicensed(int $rootId, array $configuredDomains = [], string $reason = 'no_state'): self
    {
        return new self($rootId, SiteState::Unlicensed, $reason, '', 0, false, null, null, null, null, null, [], $configuredDomains, 0, '');
    }

    /**
     * Stored state that cannot be accepted. $key is kept when the record itself
     * was cryptographically authentic (so a refresh can still be attempted with
     * it) and empty when it was not.
     *
     * @param list<string> $configuredDomains
     */
    public static function rejected(int $rootId, string $reason, array $configuredDomains = [], string $key = '', int $version = 0): self
    {
        return new self($rootId, SiteState::Invalid, $reason, '', $version, false, null, null, null, null, null, [], $configuredDomains, 0, $key);
    }

    /**
     * @param list<string> $signedDomains
     * @param list<string> $configuredDomains
     */
    public static function of(
        int $rootId,
        SiteState $state,
        string $reason,
        string $package,
        int $version,
        bool $lifetime,
        ?int $startsAt,
        ?int $expiresAt,
        ?int $issuedAt,
        ?int $verifiedAt,
        ?string $matchedDomain,
        array $signedDomains,
        array $configuredDomains,
        int $maxDomains,
        string $licenseKey,
    ): self {
        return new self(
            $rootId,
            $state,
            $reason,
            $package,
            $version,
            $lifetime,
            $startsAt,
            $expiresAt,
            $issuedAt,
            $verifiedAt,
            $matchedDomain,
            $signedDomains,
            $configuredDomains,
            $maxDomains,
            $licenseKey,
        );
    }

    /**
     * The single question every feature gate asks.
     */
    public function isActive(): bool
    {
        return $this->state === SiteState::Active;
    }

    /**
     * True when an authentic key is stored — enough to offer "update" and
     * "remove", even while entitlement is withheld.
     */
    public function hasKey(): bool
    {
        return $this->licenseKey !== '';
    }

    /**
     * Server-side only. Callers: refresh transport and the module-entry signal.
     */
    public function key(): string
    {
        return $this->licenseKey;
    }

    /**
     * The key with only its two ends legible — the one form a screen may show.
     *
     * Masking happens here rather than at the render site so the registration
     * section never has to hold the full key to say which licence is stored. A
     * key too short to keep both ends recognisable is masked whole: half of a
     * short key is not a hint, it is the key.
     */
    public function maskedKey(): string
    {
        $key = trim($this->licenseKey);
        $mask = str_repeat('•', 8);

        if ($key === '') {
            return '—';
        }

        if (mb_strlen($key) <= 8) {
            return $mask;
        }

        return mb_substr($key, 0, 4) . $mask . mb_substr($key, -4);
    }
}
