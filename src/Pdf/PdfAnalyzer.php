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

use Smalot\PdfParser\Parser;

/**
 * Reads a PDF (from the managed files/ directory) and reports its accessibility
 * basics: document title, language, and whether it is tagged.
 *
 * Safety (the project guidelines §3.4): path containment, size cap, and everything wrapped so
 * a malformed PDF NEVER crashes the scan. We deliberately do NOT rewrite PDFs —
 * a pure-PHP "fix" of arbitrary PDFs would risk corrupting tags/content, which
 * violates "nie destruktiv". Detection + guidance is the honest deliverable.
 *
 * The byte-level detectors are pure + unit-tested; the title/page read uses
 * smalot/pdfparser (handles object-stream decompression).
 */
final class PdfAnalyzer
{
    private const MAX_BYTES = 30 * 1024 * 1024;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function analyze(string $relativePath): PdfReport
    {
        $relativePath = ltrim($relativePath, '/');
        if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'pdf') {
            return new PdfReport(false, 'Keine PDF-Datei.');
        }

        $filesRoot = realpath($this->projectDir . '/files');
        $full = realpath($this->projectDir . '/' . $relativePath);

        if ($full === false || $filesRoot === false || !str_starts_with($full, $filesRoot . \DIRECTORY_SEPARATOR)) {
            return new PdfReport(false, 'Datei nicht gefunden oder ausserhalb des erlaubten Verzeichnisses.');
        }

        $size = filesize($full);
        if ($size === false || $size <= 0) {
            return new PdfReport(false, 'Datei leer oder unlesbar.');
        }
        if ($size > self::MAX_BYTES) {
            return new PdfReport(false, 'PDF zu gross fuer die Analyse.');
        }

        $bytes = @file_get_contents($full);
        if ($bytes === false) {
            return new PdfReport(false, 'PDF konnte nicht gelesen werden.');
        }

        $hasObjStm = str_contains($bytes, '/ObjStm');

        $title = '';
        $pages = 0;
        try {
            $document = (new Parser())->parseContent($bytes);
            $details = $document->getDetails();
            $title = trim((string) ($details['Title'] ?? ''));
            $pages = \count($document->getPages());
        } catch (\Throwable) {
            // Fall back to a raw byte read; never let a parser quirk crash us.
        }

        if ($title === '') {
            $title = self::titleFromBytes($bytes);
        }

        [$langState, $lang] = self::detectLang($bytes, $hasObjStm);

        return new PdfReport(
            ok: true,
            error: '',
            title: $title,
            lang: $lang,
            langState: $langState,
            tagState: self::detectTagState($bytes, $hasObjStm),
            pages: $pages,
        );
    }

    /**
     * @return array{0: string, 1: string} [langState, lang]
     */
    public static function detectLang(string $bytes, bool $hasObjStm): array
    {
        if (preg_match('#/Lang\s*\(([^)]*)\)#', $bytes, $m) === 1) {
            return ['present', trim($m[1])];
        }
        if (preg_match('#/Lang\s*<([0-9A-Fa-f]+)>#', $bytes, $m) === 1) {
            return ['present', (string) @hex2bin($m[1])];
        }

        return [$hasObjStm ? 'unknown' : 'missing', ''];
    }

    public static function detectTagState(string $bytes, bool $hasObjStm): string
    {
        if (preg_match('#/StructTreeRoot\b#', $bytes) === 1
            || preg_match('#/Marked\s+true\b#', $bytes) === 1
        ) {
            return 'tagged';
        }

        return $hasObjStm ? 'unknown' : 'untagged';
    }

    private static function titleFromBytes(string $bytes): string
    {
        if (preg_match('#/Title\s*\(([^)]*)\)#', $bytes, $m) === 1) {
            return trim(str_replace(['\\(', '\\)'], ['(', ')'], $m[1]));
        }

        return '';
    }
}
