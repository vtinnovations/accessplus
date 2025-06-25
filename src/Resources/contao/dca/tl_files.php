<?php 

/**
 * Contao Open Source CMS
 *
 *
 *
 * @package   [Access Plus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
*/


/**
 * Table tl_files
*/

$GLOBALS['TL_DCA']['tl_files']['fields']['atlPublished'] = array(
	'label'                   => &$GLOBALS['TL_LANG']['tl_files']['atlPublished'],
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array('tl_class' => 'clr'),
	'sql'                     => "INT(10) NOT NULL DEFAULT 0"
);


?>