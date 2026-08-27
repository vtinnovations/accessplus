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
 * Thrown whenever a received or stored package fails a trust check.
 *
 * The message is an INTERNAL category (e.g. "unknown_signing_key"), never a
 * user-facing text and never a place for packet content: no keys, payloads,
 * hashes, signatures or nonces may be put in here, because exceptions end up in
 * logs and stack traces. Callers translate the category into a generic
 * administrator message.
 */
final class PackageRejected extends \RuntimeException
{
    public function __construct(
        private readonly string $category,
    ) {
        parent::__construct($category);
    }

    public function category(): string
    {
        return $this->category;
    }
}
