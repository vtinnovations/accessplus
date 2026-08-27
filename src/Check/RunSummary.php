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

namespace VTInnovations\AccessPlus\Check;

/**
 * Result of one lint run. `score` is a rough 0–100 health indicator derived from
 * open finding severities — a relative trend signal, NOT a conformance metric
 * (see marketing guardrails: never imply automatic legal conformance).
 */
final class RunSummary
{
    /**
     * @param array<string, int> $bySeverity Open count keyed by severity value.
     */
    public function __construct(
        public readonly int $created,
        public readonly int $reopened,
        public readonly int $resolved,
        public readonly int $openTotal,
        public readonly array $bySeverity,
        public readonly int $score,
    ) {
    }
}
