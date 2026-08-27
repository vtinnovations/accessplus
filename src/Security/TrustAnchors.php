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

namespace VTInnovations\AccessPlus\Security;

/**
 * The pinned public verification material of the issuing service.
 *
 * Only PUBLIC keys live here — a distributed bundle never contains signing keys
 * and never contains a shared secret that could act as a trust root. The keys
 * are pinned in code (not configuration) so no site setting, database row or
 * remote response can introduce a key of its own; rotation ships as a bundle
 * update through the normal release channel.
 *
 * The material is stored in reversed fragments and reassembled at runtime. That
 * is not a security control on its own — it only removes the trivial "grep the
 * distribution for the key and patch it" shortcut. Everything that matters is
 * enforced by the signature checks in {@see DetachedSignature}.
 *
 * Fail-closed rules implemented here:
 *   - an empty/placeholder/structurally invalid ring throws
 *     `signing_key_store_empty` and never degrades to "unsigned is fine";
 *   - an unadvertised key id throws `unknown_signing_key`;
 *   - an algorithm outside the allowlist throws `unsupported_signature_algorithm`;
 *   - a key outside its activation/retirement window is not usable.
 */
final class TrustAnchors
{
    public const PURPOSE_RECORD = 'record';

    public const PURPOSE_ENVELOPE = 'envelope';

    public const PURPOSE_REQUEST = 'request';

    /** Only Ed25519 is accepted for this profile. */
    public const ALGORITHM_ED25519 = 'ed25519';

    private const ALGORITHMS = [self::ALGORITHM_ED25519];

    /** Raw key length per algorithm, used to reject truncated/placeholder material. */
    private const RAW_LENGTH = [self::ALGORITHM_ED25519 => 32];

    /**
     * Reversed fragments of the active public key. Reassembled by {@see fragments()}.
     *
     * @var list<string>
     */
    private const ACTIVE_FRAGMENTS = ['BVUF66+mgllq', 'b8GFCI86O3JF', '+1rfMj9+Rd73', '=EgySp/4'];

    private const ACTIVE_ID = 'vtone-2026a';

    /**
     * First 8 bytes (16 hex chars) of the SHA-256 over the raw public key, as
     * published for the active id. Build/readiness check only — a fingerprint is
     * never a substitute for a signature and is not consulted at runtime.
     */
    public const ACTIVE_FINGERPRINT = 'edcd614e70c59ce0';

    /**
     * @var list<array{id: string, algorithm: string, key: string, purposes: list<string>, from: int, until: int|null}>|null
     */
    private ?array $ring;

    /**
     * @param list<array{id: string, algorithm: string, key: string, purposes: list<string>, from: int, until: int|null}>|null $override
     *        Test-only replacement ring. Production code never passes this.
     */
    public function __construct(?array $override = null)
    {
        $this->ring = $override;
    }

    /**
     * Raw public key for an advertised id/algorithm/purpose triple.
     *
     * @throws PackageRejected
     */
    public function key(string $keyId, string $algorithm, string $purpose, ?int $now = null): string
    {
        $this->assertUsable();

        $algorithm = strtolower(trim($algorithm));

        if (!\in_array($algorithm, self::ALGORITHMS, true)) {
            throw new PackageRejected('unsupported_signature_algorithm');
        }

        foreach ($this->usable($purpose, $now) as $entry) {
            if (hash_equals($entry['id'], $keyId)) {
                if ($entry['algorithm'] !== $algorithm) {
                    throw new PackageRejected('signature_algorithm_mismatch');
                }

                return $entry['key'];
            }
        }

        throw new PackageRejected('unknown_signing_key');
    }

    /**
     * Every currently usable key for a purpose. The licence document itself
     * carries no key id, so its detached signature is tried against all of them.
     *
     * @return list<array{id: string, algorithm: string, key: string}>
     *
     * @throws PackageRejected
     */
    public function candidates(string $purpose, ?int $now = null): array
    {
        $this->assertUsable();

        $out = [];
        foreach ($this->usable($purpose, $now) as $entry) {
            $out[] = ['id' => $entry['id'], 'algorithm' => $entry['algorithm'], 'key' => $entry['key']];
        }

        if ($out === []) {
            throw new PackageRejected('signing_key_store_empty');
        }

        return $out;
    }

    /**
     * Rejects an empty, placeholder-only or structurally invalid ring. Called on
     * every verification and by the release readiness check, so a build with no
     * usable trust anchor can never be shipped as "working".
     *
     * @throws PackageRejected
     */
    public function assertUsable(): void
    {
        foreach ($this->entries() as $entry) {
            if ($this->isStructurallyValid($entry)) {
                return;
            }
        }

        throw new PackageRejected('signing_key_store_empty');
    }

    /**
     * Public ids in the ring — safe to show in a rotation diagnostic.
     *
     * @return list<string>
     */
    public function keyIds(): array
    {
        $out = [];
        foreach ($this->entries() as $entry) {
            $out[] = $entry['id'];
        }

        return $out;
    }

    /**
     * Truncated SHA-256 over raw key bytes, matching the published fingerprint
     * notation. Diagnostics/readiness only.
     */
    public function fingerprint(string $rawKey): string
    {
        return substr(hash('sha256', $rawKey), 0, 16);
    }

    /**
     * True when the reassembled active key still matches its published
     * fingerprint. Guards against a botched fragment edit during maintenance.
     */
    public function activeKeyMatchesFingerprint(): bool
    {
        foreach ($this->entries() as $entry) {
            if ($entry['id'] === self::ACTIVE_ID) {
                return hash_equals(self::ACTIVE_FINGERPRINT, $this->fingerprint($entry['key']));
            }
        }

        return false;
    }

    /**
     * @return list<array{id: string, algorithm: string, key: string, purposes: list<string>, from: int, until: int|null}>
     */
    private function usable(string $purpose, ?int $now): array
    {
        $now ??= time();
        $out = [];

        foreach ($this->entries() as $entry) {
            if (!$this->isStructurallyValid($entry)) {
                continue;
            }
            if (!\in_array($purpose, $entry['purposes'], true)) {
                continue;
            }
            if ($now < $entry['from']) {
                continue;
            }
            if ($entry['until'] !== null && $now >= $entry['until']) {
                continue;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param array{id: string, algorithm: string, key: string, purposes: list<string>, from: int, until: int|null} $entry
     */
    private function isStructurallyValid(array $entry): bool
    {
        if ($entry['id'] === '' || !\in_array($entry['algorithm'], self::ALGORITHMS, true)) {
            return false;
        }

        $expected = self::RAW_LENGTH[$entry['algorithm']] ?? 0;

        if ($expected <= 0 || \strlen($entry['key']) !== $expected) {
            return false;
        }

        // An all-zero key is the classic placeholder; treat it as "no key".
        return trim($entry['key'], "\0") !== '';
    }

    /**
     * @return list<array{id: string, algorithm: string, key: string, purposes: list<string>, from: int, until: int|null}>
     */
    private function entries(): array
    {
        if ($this->ring !== null) {
            return $this->ring;
        }

        $raw = CanonicalForm::fromBase64($this->fragments());

        if ($raw === null) {
            return $this->ring = [];
        }

        return $this->ring = [
            [
                'id' => self::ACTIVE_ID,
                'algorithm' => self::ALGORITHM_ED25519,
                'key' => $raw,
                'purposes' => [self::PURPOSE_RECORD, self::PURPOSE_ENVELOPE, self::PURPOSE_REQUEST],
                'from' => 0,
                'until' => null,
            ],
        ];
    }

    private function fragments(): string
    {
        $out = '';
        foreach (self::ACTIVE_FRAGMENTS as $fragment) {
            $out .= strrev($fragment);
        }

        return $out;
    }
}
