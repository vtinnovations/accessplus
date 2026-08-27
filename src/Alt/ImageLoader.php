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

use VTInnovations\AccessPlus\I18n\Text;

/**
 * Reads an image file from the Contao files directory for vision analysis.
 *
 * Security (the project guidelines §3.4): the path comes from tl_files (DB), but is still
 * treated as untrusted — the resolved real path MUST stay inside the project's
 * files/ directory (no traversal), only image extensions are allowed, and a
 * hard byte cap prevents shipping huge payloads to the AI provider.
 */
final class ImageLoader
{
    /** Vision payload cap. Providers accept more, but we minimise egress. */
    private const MAX_BYTES = 4 * 1024 * 1024;

    /** @var array<string, string> extension => mime */
    private const ALLOWED = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @throws ImageLoadException
     */
    public function load(string $relativePath): LoadedImage
    {
        $relativePath = ltrim($relativePath, '/');
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED[$extension])) {
            throw new ImageLoadException(Text::get('ai.unsupported_image_format', ['extension' => $extension]));
        }

        $filesRoot = $this->realFilesRoot();
        $full = realpath($this->projectDir . '/' . $relativePath);

        if ($full === false || !is_file($full)) {
            throw new ImageLoadException(Text::get('ai.file_not_found'));
        }

        // Containment check — defeat ../ traversal.
        if (!str_starts_with($full, $filesRoot . \DIRECTORY_SEPARATOR)) {
            throw new ImageLoadException(Text::get('ai.path_outside_allowed_dir'));
        }

        $size = filesize($full);
        if ($size === false || $size <= 0) {
            throw new ImageLoadException(Text::get('ai.file_empty_or_unreadable'));
        }
        if ($size > self::MAX_BYTES) {
            throw new ImageLoadException(Text::get('ai.image_too_large', ['size' => (int) ($size / 1024), 'max' => self::MAX_BYTES / 1024]));
        }

        $bytes = @file_get_contents($full);
        if ($bytes === false) {
            throw new ImageLoadException(Text::get('ai.image_unreadable'));
        }

        return new LoadedImage(
            mime: self::ALLOWED[$extension],
            base64: base64_encode($bytes),
            bytes: $size,
            relativePath: $relativePath,
        );
    }

    private function realFilesRoot(): string
    {
        $root = realpath($this->projectDir . '/files');

        // Fall back to a non-matching sentinel so containment fails closed when
        // files/ is missing, rather than allowing everything.
        return $root !== false ? $root : $this->projectDir . '/__no_files_dir__';
    }
}
