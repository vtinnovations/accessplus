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

namespace VTInnovations\AccessPlus\Pdf;

/**
 * Result of a PDF scan run: how many PDFs were checked, how many concrete issues
 * were recorded, how many checks were inconclusive (compressed → unknown), and
 * how many could not be read.
 */
final class PdfScanSummary
{
    public function __construct(
        public readonly int $checked,
        public readonly int $issues,
        public readonly int $unknown,
        public readonly int $unreadable,
    ) {
    }
}
