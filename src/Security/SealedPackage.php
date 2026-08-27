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
 * An authenticated package: the EXACT received bytes, the parsed document, and
 * the signed envelope that pins those bytes by digest.
 *
 * `bytes` is authoritative. The parsed `document` is a convenience view for the
 * policy layer and must never be re-serialized and stored back — a re-encode
 * would change the byte sequence the digest and signature were made over.
 */
final class SealedPackage
{
    /**
     * @param array<string, mixed> $document parsed view of $bytes, never re-serialized for storage
     * @param array<string, mixed> $envelope signed integrity envelope, incl. its own signature
     */
    public function __construct(
        public readonly string $bytes,
        public readonly array $document,
        public readonly array $envelope,
    ) {
    }

    public function version(): int
    {
        return (int) ($this->document['license_version'] ?? 0);
    }

    /**
     * Public id of the key that signed the envelope. Safe for rotation
     * diagnostics; it is not secret material.
     */
    public function keyId(): string
    {
        return (string) ($this->envelope['key_id'] ?? '');
    }
}
