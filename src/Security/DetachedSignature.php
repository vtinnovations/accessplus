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
 * Detached signature check over canonical bytes.
 *
 * Deliberately minimal and fail-closed: an unsupported algorithm, a missing
 * runtime primitive, a malformed key or a malformed signature is a rejection —
 * never a "skip the check" path. Missing libsodium must not silently turn the
 * bundle into an unverified client, so it throws its own category instead of
 * returning false (the distinction matters for the administrator diagnostic).
 */
final class DetachedSignature
{
    /** Raw signature length for Ed25519. */
    private const SIGNATURE_LENGTH = 64;

    /**
     * @throws PackageRejected when the runtime, algorithm or encoding is unusable
     */
    public static function verify(string $message, string $signature, string $publicKey, string $algorithm): bool
    {
        if (strtolower(trim($algorithm)) !== TrustAnchors::ALGORITHM_ED25519) {
            throw new PackageRejected('unsupported_signature_algorithm');
        }

        if (!\function_exists('sodium_crypto_sign_verify_detached')) {
            throw new PackageRejected('signature_runtime_unavailable');
        }

        if (\strlen($publicKey) !== 32) {
            throw new PackageRejected('malformed_signing_key');
        }

        $raw = self::decode($signature);

        if ($raw === null) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($raw, $message, $publicKey);
        } catch (\SodiumException) {
            return false;
        }
    }

    /**
     * Signatures arrive Base64-encoded. Lowercase hex of the exact raw length is
     * accepted as well because both notations are unambiguous at 64 raw bytes;
     * anything else is rejected rather than "best effort" decoded.
     */
    private static function decode(string $signature): ?string
    {
        $signature = trim($signature);

        if ($signature === '') {
            return null;
        }

        $raw = CanonicalForm::fromBase64($signature);

        if ($raw === null
            && \strlen($signature) === self::SIGNATURE_LENGTH * 2
            && preg_match('/^[0-9a-f]+$/', $signature) === 1
        ) {
            $hex = hex2bin($signature);
            $raw = \is_string($hex) ? $hex : null;
        }

        if ($raw === null || \strlen($raw) !== self::SIGNATURE_LENGTH) {
            return null;
        }

        return $raw;
    }
}
