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

namespace VTInnovations\AccessPlus\Subtitle;

/**
 * Result of a Whisper transcription: ready-to-store WebVTT plus provenance.
 * Value object — no behaviour.
 */
final class TranscriptResult
{
    public function __construct(
        public readonly string $vtt,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $durationMs,
    ) {
    }
}
