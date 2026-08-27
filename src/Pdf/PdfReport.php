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
 * Result of analysing one PDF. `langState`/`tagState` are deliberately tri-state
 * (present|missing|unknown / tagged|untagged|unknown): a compressed PDF can hide
 * its catalog in an object stream, so we report "unknown" rather than a false
 * "missing" — honesty over false positives.
 */
final class PdfReport
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $error = '',
        public readonly string $title = '',
        public readonly string $lang = '',
        public readonly string $langState = 'unknown',
        public readonly string $tagState = 'unknown',
        public readonly int $pages = 0,
    ) {
    }

    public function hasTitle(): bool
    {
        return trim($this->title) !== '';
    }
}
