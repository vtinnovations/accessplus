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

use VTInnovations\AccessPlus\Controller\AltModule;
use VTInnovations\AccessPlus\Controller\AuditModule;
use VTInnovations\AccessPlus\Controller\DashboardModule;
use VTInnovations\AccessPlus\Controller\FrontendScanModule;
use VTInnovations\AccessPlus\Controller\HubModule;
use VTInnovations\AccessPlus\Controller\OverlayModule;
use VTInnovations\AccessPlus\Controller\PdfModule;
use VTInnovations\AccessPlus\Controller\ReportModule;
use VTInnovations\AccessPlus\Controller\SettingsModule;
use VTInnovations\AccessPlus\Controller\StatementModule;
use VTInnovations\AccessPlus\Controller\SimplifyModule;
use VTInnovations\AccessPlus\Controller\SubtitleModule;
use VTInnovations\AccessPlus\FrontendModule\FeedbackFrontendModule;
use VTInnovations\AccessPlus\FrontendModule\SimpleSwitchModule;
use VTInnovations\AccessPlus\FrontendModule\StatementFrontendModule;
use VTInnovations\AccessPlus\Model\AltSuggestionModel;
use VTInnovations\AccessPlus\Model\AriaFixModel;
use VTInnovations\AccessPlus\Model\AuditModel;
use VTInnovations\AccessPlus\Model\FeedbackModel;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\Model\RunModel;
use VTInnovations\AccessPlus\Model\SimplificationModel;
use VTInnovations\AccessPlus\Model\TrackModel;

/*
 * Backend modules, registered as classic BE_MOD callbacks so they behave
 * identically on Contao 4.13 and 5.x. Contao instantiates the class and calls
 * generate(). The feedback module is a plain DCA table (list/edit).
 */
// Single compact nav group: one hub (tabbed) + the reports DCA list.
$GLOBALS['BE_MOD']['accessibility']['accessplus'] = [
    'callback' => HubModule::class,
];

$GLOBALS['BE_MOD']['accessibility']['accessplus_feedback'] = [
    'tables' => ['tl_accessplus_feedback'],
];

/*
 * Frontend modules: the public accessibility statement and the barrier feedback
 * form. Classic FE_MOD registration (portable 4.13/5.x).
 */
$GLOBALS['FE_MOD']['accessibility']['accessplusStatement'] = StatementFrontendModule::class;
$GLOBALS['FE_MOD']['accessibility']['accessplusFeedback'] = FeedbackFrontendModule::class;
$GLOBALS['FE_MOD']['accessibility']['accessplusSimpleSwitch'] = SimpleSwitchModule::class;

// Register models for their tables.
$GLOBALS['TL_MODELS']['tl_accessplus_finding'] = FindingModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_altsuggestion'] = AltSuggestionModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_run'] = RunModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_track'] = TrackModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_simplification'] = SimplificationModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_audit'] = AuditModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_ariafix'] = AriaFixModel::class;
$GLOBALS['TL_MODELS']['tl_accessplus_feedback'] = FeedbackModel::class;
