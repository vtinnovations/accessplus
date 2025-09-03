<?php

/* 
 * @package   [Access Plus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */

use VTInnovations\Accessplus\Hooks\CustomHooks;
use VTInnovations\Accessplus\Backend\AccessbilityAnalysis;

/**
 * FE MODULES
 */
$GLOBALS['FE_MOD']['accessplus'] = array(
	'accessibility' 	=> 'VTInnovations\Accessplus\FeMod\Accessibility'
);

/**
 * BE MODULES
 */
$GLOBALS['BE_MOD']['system']['accessbility_analysis'] = array(
	'callback' 	=> AccessbilityAnalysis::class
);


/**
 * HOOKS
 */
$GLOBALS['TL_HOOKS']['outputFrontendTemplate'][] = array(CustomHooks::class, 'outputFrontendTemplate');
$GLOBALS['TL_HOOKS']['getSystemMessages'][] = array(CustomHooks::class, 'getSystemMessages');
