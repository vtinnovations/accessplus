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
 * Pure, DB-free detector for non-descriptive link text ("hier klicken",
 * "read more"). Kept separate from the DB check so the language logic is unit
 * testable without a database (WCAG 2.4.4).
 */
final class VagueLinkText
{
    /**
     * Phrases that carry no destination meaning out of context. German + English
     * since Contao sites are frequently bilingual.
     *
     * @var list<string>
     */
    private const VAGUE = [
        'hier', 'hier klicken', 'klick hier', 'klicke hier', 'klicken sie hier',
        'mehr', 'mehr erfahren', 'mehr dazu', 'weiterlesen', 'weiter', 'weitere infos',
        'link', 'klick', 'download', 'mehr lesen', 'zum artikel', 'artikel lesen',
        'click here', 'read more', 'more', 'here', 'learn more', 'this link',
        'click', 'details', 'info', 'go', 'continue',
    ];

    public static function isVague(string $text): bool
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return false;
        }

        return \in_array($normalized, self::VAGUE, true);
    }

    /**
     * Lowercase, strip surrounding punctuation/whitespace, collapse inner runs.
     */
    public static function normalize(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text, " \t\n\r\0\x0B.!?:;»«\"'›‹-–—…");
    }
}
