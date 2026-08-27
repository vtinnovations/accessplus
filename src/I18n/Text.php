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

namespace VTInnovations\AccessPlus\I18n;

use Contao\Controller;

/**
 * Single lookup point for every user-facing string in this bundle.
 *
 * Backed by the classic $GLOBALS['TL_LANG']['accessplus'] array (portable
 * 4.13/5.x, same mechanism the bundle already uses for modules.php/tl_page.php),
 * loaded from contao/languages/{language}/accessplus.php.
 *
 * Contao's Controller::loadLanguageFile() always loads the English file first as
 * a fallback and then overlays the requested language on top, so a key missing
 * from a translation still resolves to its English text instead of vanishing.
 * English is therefore the complete, authoritative set; every other language is
 * a translation of it and may lag behind without breaking anything.
 *
 * Works identically in a backend request (current backend user's language), a
 * frontend request (current page's language) and on the CLI (Contao's
 * configured default, normally English) — callers never need to know which.
 *
 * Deliberately defensive about ONE thing: Symfony Console calls configure() on
 * every registered command merely to build `list`/`--help` output, and that can
 * happen before Contao's framework is initialized for that command (no request,
 * no booted container yet). Controller::loadLanguageFile() then hits a null
 * container and throws a hard \Error — which would take down the entire console,
 * not just this bundle. A translation lookup must never be able to do that, so
 * the load is wrapped and a failure degrades to whatever is already in
 * $GLOBALS['TL_LANG']['accessplus'] (typically nothing yet, so callers see the
 * '[[key]]' sentinel) instead of crashing the process. Once the framework boots
 * for real, the next call loads normally — nothing here caches the failure.
 */
final class Text
{
    /**
     * @param array<string, scalar> $params substituted into %name% placeholders
     */
    public static function get(string $key, array $params = []): string
    {
        try {
            Controller::loadLanguageFile('accessplus');
        } catch (\Throwable) {
            // Framework not booted yet for this invocation (see class docblock).
            // Fall through and resolve against whatever is already loaded.
        }

        $value = self::resolve($key);

        if ($value === null) {
            // Visible and grep-able in the UI, never a blank — a missing key is a
            // release bug, and this makes it impossible to miss.
            return '[[' . $key . ']]';
        }

        if ($params === []) {
            return $value;
        }

        $replacements = [];
        foreach ($params as $name => $param) {
            $replacements['%' . $name . '%'] = (string) $param;
        }

        return strtr($value, $replacements);
    }

    private static function resolve(string $key): ?string
    {
        $node = $GLOBALS['TL_LANG']['accessplus'] ?? null;

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return \is_string($node) ? $node : null;
    }
}
