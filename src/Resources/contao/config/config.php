<?php

/* 
 * @package   [Access Plus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */

use VTInnovations\Accessplus\Hooks\CustomHooks;

/**
 * FE MODULES
 */
$GLOBALS['FE_MOD']['accessibility'] = array(
	'accessibility' 	=> 'VTInnovations\Accessplus\FeMod\Accessibility'
);

/**
 * HOOKS
 */
$GLOBALS['TL_HOOKS']['outputFrontendTemplate'][] = array(CustomHooks::class, 'outputFrontendTemplate');
