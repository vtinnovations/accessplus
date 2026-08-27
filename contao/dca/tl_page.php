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

use VTInnovations\AccessPlus\Controller\RootRegistrationSection;

/*
 * Registration section for site roots (Modell 2: one licence per Startpunkt).
 *
 * Placement: its own section in the ROOT page palettes only, inserted directly
 * above Contao's access-protection section. Ordinary pages are untouched, and no
 * existing field, palette entry or callback of tl_page is replaced — everything
 * here is additive.
 *
 * The field is virtual: it has no `sql`, so it creates no database column and
 * cannot become a second, competing copy of the authoritative state. The state
 * lives exclusively in the bundle's private var/ store.
 */

$dca = &$GLOBALS['TL_DCA']['tl_page'];

// Callbacks are registered as [class, method] arrays (not attributes) because
// Contao instantiates them itself; both entries are appended, never overwritten.
$dca['config']['onsubmit_callback'][] = [RootRegistrationSection::class, 'onSubmit'];

$dca['fields']['accessplus_registration'] = [
    'exclude' => true,
    'input_field_callback' => [RootRegistrationSection::class, 'render'],
    'eval' => ['tl_class' => 'clr', 'doNotCopy' => true],
];

/*
 * Insert the section above the access-protection section of every root palette
 * ('root' and, on Contao 5, 'rootfallback'). If that anchor should ever be
 * renamed by the core, the section is appended instead of silently vanishing.
 */
$anchor = '{protected_legend';
$section = '{accessplus_license_legend},accessplus_registration;';

foreach (['root', 'rootfallback'] as $paletteName) {
    if (!isset($dca['palettes'][$paletteName]) || !\is_string($dca['palettes'][$paletteName])) {
        continue;
    }

    $palette = $dca['palettes'][$paletteName];

    if (str_contains($palette, 'accessplus_registration')) {
        continue;
    }

    $position = strpos($palette, $anchor);

    if ($position !== false) {
        $palette = substr($palette, 0, $position) . $section . substr($palette, $position);
    } else {
        $palette .= ';' . rtrim($section, ';');
    }

    $dca['palettes'][$paletteName] = $palette;
}

unset($dca, $anchor, $section, $palette, $paletteName, $position);
