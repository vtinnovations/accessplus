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

namespace VTInnovations\AccessPlus\Check\Analysis;

/**
 * Pure heading-order analysis (WCAG 1.3.1 / 2.4.6). Operates on an ordered list
 * of heading levels within one container (e.g. an article) and reports the two
 * deterministic, non-judgemental problems the DB can see:
 *
 *   - a skipped level (e.g. h2 directly followed by h4), and
 *   - more than one h1 in the same container.
 *
 * Subjective questions ("is this heading meaningful?") are out of scope —
 * those belong to the human-review track.
 */
final class HeadingHierarchy
{
    /**
     * @param list<array{index: int, level: int}> $headings Ordered as they appear.
     *
     * @return list<array{index: int, level: int, problem: string}>
     */
    public static function analyze(array $headings): array
    {
        $issues = [];
        $previousLevel = null;
        $h1Count = 0;

        foreach ($headings as $heading) {
            $level = $heading['level'];

            if ($level === 1) {
                ++$h1Count;
                if ($h1Count > 1) {
                    $issues[] = [
                        'index' => $heading['index'],
                        'level' => $level,
                        'problem' => 'multiple_h1',
                    ];
                }
            }

            if ($previousLevel !== null && $level > $previousLevel + 1) {
                $issues[] = [
                    'index' => $heading['index'],
                    'level' => $level,
                    'problem' => 'skipped_level',
                ];
            }

            $previousLevel = $level;
        }

        return $issues;
    }
}
