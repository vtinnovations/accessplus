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

namespace VTInnovations\AccessPlus\Monitor;

/**
 * Change since the previous run: how many findings appeared, how many were
 * resolved, and the score movement. `hasPrevious` is false on the very first
 * run (nothing to compare against).
 */
final class Delta
{
    public function __construct(
        public readonly bool $hasPrevious,
        public readonly int $newCount,
        public readonly int $resolvedCount,
        public readonly int $scoreDelta,
    ) {
    }
}
