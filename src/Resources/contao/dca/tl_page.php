<?php

/**
 * Contao Open Source CMS
 *
 *
 * @package   [Accessplus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
*/

/**
 * Table tl_page
*/
$GLOBALS['TL_DCA']['tl_page']['palettes']['__selector__'][] = 'enableAltTag';
$GLOBALS['TL_DCA']['tl_page']['palettes']['root'] = str_replace('{publish_legend},published,start,stop', '{accessibility_legend:hide},accessibility_licence,backgroundColor,enableAltTag ;{publish_legend},published,start,stop', $GLOBALS['TL_DCA']['tl_page']['palettes']['root']);
$GLOBALS['TL_DCA']['tl_page']['palettes']['rootfallback'] = str_replace('{publish_legend},published,start,stop', '{accessibility_legend:hide},accessibility_licence,backgroundColor,enableAltTag;{publish_legend},published,start,stop', $GLOBALS['TL_DCA']['tl_page']['palettes']['rootfallback']);
$GLOBALS['TL_DCA']['tl_page']['subpalettes']['enableAltTag'] = 'openApiKey';

$GLOBALS['TL_DCA']['tl_page']['fields']['accessibility_licence'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_page']['accessibility_licence'],
	'exclude'       => true,
	'inputType'     => 'text',
	'eval'          => array( 'tl_class' => 'clr', 'tl_class' => 'w50'),
	'sql'           =>  "TEXT NULL"
];
$GLOBALS['TL_DCA']['tl_page']['fields']['backgroundColor'] = array(
    'label'        => &$GLOBALS['TL_LANG']['tl_page']['backgroundColor'],
    'exclude'      => true,
    'inputType'    => 'text',
    'eval'         => array('maxlength' => 11, 'colorpicker' => true, 'isHexColor' => true, 'decodeEntities' => true, 'tl_class' => 'w50 wizard', 'mandatory' => false),
    'sql'          => "Varchar(255) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_page']['fields']['enableAltTag'] = array(
    'label'        => &$GLOBALS['TL_LANG']['tl_page']['enableAltTag'],
    'inputType'    => 'checkbox',
    'eval'         => array('submitOnChange'=>true, 'tl_class' => 'w50'),
    'sql'          => array('type' => 'boolean', 'default' => false)
);
$GLOBALS['TL_DCA']['tl_page']['fields']['openApiKey'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_page']['openApiKey'],
	'exclude'       => true,
	'inputType'     => 'text',
	'eval'          => array( 'tl_class' => 'clr', 'tl_class' => 'w50'),
	'sql'           =>  "VARCHAR (100) NOT NULL default ''"
];

?>
