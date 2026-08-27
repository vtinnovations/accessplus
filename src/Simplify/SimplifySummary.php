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

namespace VTInnovations\AccessPlus\Simplify;

/**
 * Outcome of a simplification batch. Value object.
 */
final class SimplifySummary
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
