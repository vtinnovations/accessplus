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

use Symfony\Component\HttpFoundation\Request;

/**
 * Authenticates a server-to-server callback.
 *
 * Such a request arrives without a backend session and without a CSRF token —
 * so the ONLY thing that may establish trust is the cryptographic signature over
 * method, path, request id, timestamp, nonce and the hash of the exact raw body.
 * A claimed Origin/Referer, a user agent, a source IP or a reverse DNS name are
 * not trust, and none of them appear in this class.
 *
 * The key-id header selects the pinned key but is intentionally not part of the
 * signed message; the duplicated metadata in headers and body must still agree
 * exactly, which is checked by the caller.
 */
final class CallbackAuthenticator
{
    /** Accepted clock difference for the signed timestamp. */
    private const WINDOW = 300;

    public function __construct(
        private readonly TrustAnchors $anchors,
    ) {
    }

    /**
     * @return array{request_id: string, timestamp: int, nonce: string, key_id: string}
     *
     * @throws PackageRejected
     */
    public function authenticate(Request $request, string $rawBody, string $expectedPath): array
    {
        $requestId = trim((string) $request->headers->get('X-VT-Request-ID', ''));
        $timestamp = trim((string) $request->headers->get('X-VT-Timestamp', ''));
        $nonce = trim((string) $request->headers->get('X-VT-Nonce', ''));
        $keyId = trim((string) $request->headers->get('X-VT-Key-ID', ''));
        $signature = trim((string) $request->headers->get('X-VT-Signature', ''));

        if ($requestId === '' || $timestamp === '' || $nonce === '' || $keyId === '' || $signature === '') {
            throw new PackageRejected('callback_unsigned');
        }

        if (preg_match('/^[0-9]{1,12}$/', $timestamp) !== 1) {
            throw new PackageRejected('callback_timestamp_invalid');
        }

        // Shape only: a safe character set and an upper bound, so these values
        // cannot carry control characters or bloat the replay journal. No minimum
        // length is imposed — the issuer owns the format of its identifiers, and
        // rejecting a correctly signed request over its id length would break
        // licence updates for a rule this protocol never defined. Authenticity
        // comes from the signature; single-use comes from the journal.
        if (preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $requestId) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{1,256}$/', $nonce) !== 1
        ) {
            throw new PackageRejected('callback_metadata_invalid');
        }

        $signedAt = (int) $timestamp;

        if (abs(time() - $signedAt) > self::WINDOW) {
            throw new PackageRejected('callback_timestamp_out_of_window');
        }

        $path = $request->getPathInfo();

        if (!hash_equals($expectedPath, $path)) {
            throw new PackageRejected('callback_path_mismatch');
        }

        $key = $this->anchors->key($keyId, TrustAnchors::ALGORITHM_ED25519, TrustAnchors::PURPOSE_REQUEST);

        $message = CanonicalForm::request(
            $request->getMethod(),
            $path,
            $requestId,
            $signedAt,
            $nonce,
            CanonicalForm::bodyHash($rawBody),
        );

        if (!DetachedSignature::verify($message, $signature, $key, TrustAnchors::ALGORITHM_ED25519)) {
            throw new PackageRejected('callback_signature_invalid');
        }

        return [
            'request_id' => $requestId,
            'timestamp' => $signedAt,
            'nonce' => $nonce,
            'key_id' => $keyId,
        ];
    }
}
