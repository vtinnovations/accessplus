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
 * Result of a batch generation run. `blocked` is true when the egress
 * kill-switch stopped the run before any network call.
 */
final class AltSummary
{
    public function __construct(
        public readonly bool $blocked,
        public readonly int $generated,
        public readonly int $skipped,
        public readonly int $errors,
        public readonly string $message,
    ) {
    }
}
