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
 * An image loaded for vision analysis: its MIME type and base64 payload (no
 * data: prefix), ready to drop into a PromptBundle.
 */
final class LoadedImage
{
    public function __construct(
        public readonly string $mime,
        public readonly string $base64,
        public readonly int $bytes,
        public readonly string $relativePath,
    ) {
    }
}
