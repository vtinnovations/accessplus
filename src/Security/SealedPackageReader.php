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
 * Opens a sealed package in the one order that is safe.
 *
 * The digest inside the envelope is only a tamper tripwire — it proves nothing
 * on its own, because anyone editing the document can recompute an MD5. So the
 * ENVELOPE SIGNATURE is verified first; only then is its digest trusted, and
 * only then is it compared against the exact received bytes. The document is
 * parsed afterwards, and its own detached signature is verified over the
 * canonical form of the parsed members.
 *
 * Everything throws {@see PackageRejected} with an internal category. No packet
 * content ever leaves this class.
 */
final class SealedPackageReader
{
    /** Hard ceiling for a decoded document; a legitimate one is a few hundred bytes. */
    private const MAX_DOCUMENT_BYTES = 131072;

    private const REQUIRED_ENVELOPE_FIELDS = [
        'project',
        'project_slug',
        'license_version',
        'license_md5',
        'generated_at',
        'key_id',
        'signature_algorithm',
        'signature',
    ];

    public function __construct(
        private readonly TrustAnchors $anchors,
    ) {
    }

    /**
     * Opens a freshly received response body (activation, refresh or callback).
     *
     * @param array<string, mixed> $payload
     *
     * @throws PackageRejected
     */
    public function open(array $payload): SealedPackage
    {
        $b64 = $payload['license_payload_b64'] ?? null;
        $envelope = $payload['integrity'] ?? null;

        if (!\is_string($b64) || !\is_array($envelope)) {
            throw new PackageRejected('malformed_package');
        }

        $bytes = CanonicalForm::fromBase64($b64);

        if ($bytes === null) {
            throw new PackageRejected('malformed_payload_encoding');
        }

        /** @var array<string, mixed> $envelope */
        return $this->verify($bytes, $envelope);
    }

    /**
     * Re-opens locally stored state. Identical checks: state on disk is treated
     * exactly like state off the wire, so a hand-edited file cannot be trusted
     * just because it already lives in the private state directory.
     *
     * @param array<string, mixed> $envelope
     *
     * @throws PackageRejected
     */
    public function reopen(string $bytes, array $envelope): SealedPackage
    {
        return $this->verify($bytes, $envelope);
    }

    /**
     * @param array<string, mixed> $envelope
     *
     * @throws PackageRejected
     */
    private function verify(string $bytes, array $envelope): SealedPackage
    {
        $this->anchors->assertUsable();

        if ($bytes === '' || \strlen($bytes) > self::MAX_DOCUMENT_BYTES) {
            throw new PackageRejected('payload_size_rejected');
        }

        foreach (self::REQUIRED_ENVELOPE_FIELDS as $field) {
            if (!\array_key_exists($field, $envelope)) {
                throw new PackageRejected('malformed_envelope');
            }
        }

        $algorithm = (string) $envelope['signature_algorithm'];
        $keyId = (string) $envelope['key_id'];
        $signature = $envelope['signature'];

        if (!\is_string($signature) || $signature === '') {
            throw new PackageRejected('malformed_envelope');
        }

        // 1. Envelope signature — before anything inside the envelope is trusted.
        $key = $this->anchors->key($keyId, $algorithm, TrustAnchors::PURPOSE_ENVELOPE);

        if (!DetachedSignature::verify(CanonicalForm::document($envelope), $signature, $key, $algorithm)) {
            throw new PackageRejected('envelope_signature_invalid');
        }

        // 2. Exact-byte tripwire, now that the expected digest is authenticated.
        $expected = $envelope['license_md5'];

        if (!\is_string($expected) || !CanonicalForm::equals(strtolower($expected), md5($bytes))) {
            throw new PackageRejected('payload_digest_mismatch');
        }

        // 3. Parse the exact bytes. The parsed view is never written back.
        try {
            $document = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PackageRejected('malformed_document');
        }

        if (!\is_array($document) || $document === [] || array_is_list($document)) {
            throw new PackageRejected('malformed_document');
        }

        // 4. Document signature. The document names no key id, so every usable
        //    record-purpose anchor is tried.
        $documentSignature = $document['signature'] ?? null;

        if (!\is_string($documentSignature) || $documentSignature === '') {
            throw new PackageRejected('document_signature_missing');
        }

        $message = CanonicalForm::document($document);
        $verified = false;

        foreach ($this->anchors->candidates(TrustAnchors::PURPOSE_RECORD) as $candidate) {
            if (DetachedSignature::verify($message, $documentSignature, $candidate['key'], $candidate['algorithm'])) {
                $verified = true;

                break;
            }
        }

        if (!$verified) {
            throw new PackageRejected('document_signature_invalid');
        }

        // 5. Envelope and document must describe the same package version.
        if ((int) $envelope['license_version'] !== (int) ($document['license_version'] ?? -1)) {
            throw new PackageRejected('envelope_document_mismatch');
        }

        /** @var array<string, mixed> $document */
        /** @var array<string, mixed> $envelope */
        return new SealedPackage($bytes, $document, $envelope);
    }
}
