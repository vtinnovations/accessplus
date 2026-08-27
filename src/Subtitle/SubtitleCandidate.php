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

use VTInnovations\AccessPlus\Model\TrackModel;

/**
 * One media file that can receive subtitles, with its current status. View model
 * for the backend subtitle screen.
 */
final class SubtitleCandidate
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $path,
        public readonly string $name,
        public readonly int $sizeBytes,
        public readonly bool $used,
        public readonly ?TrackModel $track,
    ) {
    }

    public function tooLarge(): bool
    {
        return $this->sizeBytes > 25 * 1024 * 1024;
    }
}
