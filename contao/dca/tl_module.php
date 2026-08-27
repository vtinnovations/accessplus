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

/*
 * Frontend module palettes for the accessibility statement and the barrier
 * feedback channel. Additive — no existing tl_module fields are changed.
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['accessplusStatement'] =
    '{title_legend},name,headline,type;{protected_legend:hide},protected,guests;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['accessplusFeedback'] =
    '{title_legend},name,headline,type;{protected_legend:hide},protected,guests;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['accessplusSimpleSwitch'] =
    '{title_legend},name,headline,type;{protected_legend:hide},protected,guests;{expert_legend:hide},cssID';
