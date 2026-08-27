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

namespace VTInnovations\AccessPlus\Alt;

/**
 * The model's proposal for one image+language: whether it is decorative (→ an
 * intentionally empty alt) and, if not, the suggested alt text.
 *
 * The text is UNTRUSTED model output — stored as plaintext, escaped on output,
 * never written live without human approval (the project guidelines §3.2, golden rule).
 */
final class AltProposal
{
    public function __construct(
        public readonly bool $decorative,
        public readonly string $alt,
    ) {
    }

    /**
     * The alt value to persist on approval: empty string for decorative images
     * (a valid, intentional empty alt), otherwise the trimmed suggestion.
     */
    public function altForStorage(): string
    {
        return $this->decorative ? '' : trim($this->alt);
    }
}
