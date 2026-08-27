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

namespace VTInnovations\AccessPlus\Overlay;

use VTInnovations\AccessPlus\I18n\Text;

/**
 * Single source of truth for the comfort-overlay feature catalogue. The backend
 * uses it to render per-feature on/off switches; the injection listener uses it
 * to compute the enabled id list passed to the frontend widget (which renders
 * only the enabled features).
 *
 * Honesty note (Bauplan Marketing-Leitplanke): these are comfort/display
 * options, never a substitute for an accessible site.
 *
 * Labels are resolved through Text::get() at read time so the catalogue itself
 * only carries stable ids — see contao/languages/{en,de}/accessplus.php,
 * `overlay_group.*` and `overlay_feature.*`.
 */
final class OverlayFeatures
{
    /**
     * group key => [group language key, [feature ids]].
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const GROUPS = [
        'profiles' => ['overlay_group.profiles', [
            'profile_epilepsy', 'profile_lowvision', 'profile_adhd',
        ]],
        'reading' => ['overlay_group.reading', [
            'contentscale', 'fontsize', 'lineheight', 'letterspacing', 'readablefont',
            'dyslexiafont', 'highlighttitles', 'highlightlinks', 'bionic', 'linknav',
        ]],
        'orientation' => ['overlay_group.orientation', [
            'darkcontrast', 'lightcontrast', 'highcontrast', 'monochrome', 'stopanim',
            'mutesound', 'bigcursor', 'hideimages', 'readingguide', 'tts',
            'focushighlight', 'hoverhighlight', 'textalign',
        ]],
        'colors' => ['overlay_group.colors', [
            'color_text', 'color_title', 'color_link', 'color_bg',
        ]],
    ];

    /**
     * Resolved for rendering: group key => [translated group label, [id => translated feature label, ...]].
     *
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function groups(): array
    {
        $out = [];
        foreach (self::GROUPS as $groupKey => [$groupLabelKey, $ids]) {
            $features = [];
            foreach ($ids as $id) {
                $features[$id] = Text::get('overlay_feature.' . $id);
            }
            $out[$groupKey] = [Text::get($groupLabelKey), $features];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function allIds(): array
    {
        $ids = [];
        foreach (self::GROUPS as [, $featureIds]) {
            foreach ($featureIds as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
