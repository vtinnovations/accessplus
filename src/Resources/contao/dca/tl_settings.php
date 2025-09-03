<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2025 - 2030 V&T Innovations LLP
 *
 * PHP version 7
 * @package   [accessplus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */

/**
 * Table tl_settings
 */

$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'enable_accessibility';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace('{chmod_legend},defaultUser,defaultGroup,defaultChmod', '{chmod_legend},defaultUser,defaultGroup,defaultChmod;{accessibility_legend:hide},waveApi,enable_accessibility', $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']);
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['enable_accessibility'] = 'accessibility_all_text,accessibility_title_text,accessibility_content_text,accessibility_link_text,accessibility_content_background,text_font,alignment,contrast,highlight,big_cursor,hide_images,enable_underline,reading_mask,animation,mute_sound,epilepsy,visually_impaired,cognitive_disability,ADHD_friendly_mode,blindness_mode,online_dictionary,reading_aid,text_to_speech,negative_contrast,content_scalling,textlupe,font_size,line_height,letter_spacing,link_navigator,highlight_focus,mark_hover,cognitive_reading,keyboard_navigation,high_contrast,black_white,';

$GLOBALS['TL_DCA']['tl_settings']['fields']['enable_accessibility'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['enable_accessibility'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('submitOnChange'=>true),
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['accessibility_title_text'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['accessibility_title_text'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['maroon' => 'Maroon','red' => 'Red','aqua' => 'Aqua','orange' => 'Orange','yellow' => 'Yellow','olive' => 'Olive','green' => 'Green','purple' => 'Purple','fuchsia' => 'Fuchsia','lime' => 'Lime','teal' => 'Teal','blue' => 'Blue','navy' => 'Navy','black' => 'Black','white' => 'White'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['accessibility_content_text'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['accessibility_content_text'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['maroon' => 'Maroon','red' => 'Red','aqua' => 'Aqua','orange' => 'Orange','yellow' => 'Yellow','olive' => 'Olive','green' => 'Green','purple' => 'Purple','fuchsia' => 'Fuchsia','lime' => 'Lime','teal' => 'Teal','blue' => 'Blue','navy' => 'Navy','black' => 'Black','white' => 'White'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['accessibility_content_background'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['accessibility_content_background'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['maroon' => 'Maroon','red' => 'Red','aqua' => 'Aqua','orange' => 'Orange','yellow' => 'Yellow','olive' => 'Olive','green' => 'Green','purple' => 'Purple','fuchsia' => 'Fuchsia','lime' => 'Lime','teal' => 'Teal','blue' => 'Blue','navy' => 'Navy','black' => 'Black','white' => 'White'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['accessibility_link_text'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['accessibility_link_text'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['maroon' => 'Maroon','red' => 'Red','aqua' => 'Aqua','orange' => 'Orange','yellow' => 'Yellow','olive' => 'Olive','green' => 'Green','purple' => 'Purple','fuchsia' => 'Fuchsia','lime' => 'Lime','teal' => 'Teal','blue' => 'Blue','navy' => 'Navy','black' => 'Black','white' => 'White'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['accessibility_all_text'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['accessibility_all_text'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['maroon' => 'Maroon','red' => 'Red','aqua' => 'Aqua','orange' => 'Orange','yellow' => 'Yellow','olive' => 'Olive','green' => 'Green','purple' => 'Purple','fuchsia' => 'Fuchsia','lime' => 'Lime','teal' => 'Teal','blue' => 'Blue','navy' => 'Navy','black' => 'Black','white' => 'White'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['text_font'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['text_font'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['readable' => 'readable','dyslexia' => 'dyslexia'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['alignment'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['alignment'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['left' => 'Left','center' => 'Center', 'right' => 'Right'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['contrast'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['contrast'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['dark' => 'Dark','light' => 'Light'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['highlight'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['highlight'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['titles' => 'Titles','links' => 'Links'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['big_cursor'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['big_cursor'],
	'exclude'		=> true,
	'inputType'		=> 'select',
	'options'		=> ['black' => 'Black','white' => 'White'],
	'eval'			=> ['multiple' => true,'tl_class' => 'w50','csv' => ',','chosen' => true],
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['hide_images'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['hide_images'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['animation'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['animation'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['mute_sound'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['mute_sound'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['epilepsy'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['epilepsy'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['visually_impaired'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['visually_impaired'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['enable_underline'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['enable_underline'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['reading_mask'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['reading_mask'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['keyboard_navigation'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['keyboard_navigation'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['cognitive_disability'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['cognitive_disability'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['ADHD_friendly_mode'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['ADHD_friendly_mode'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['blindness_mode'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['blindness_mode'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['online_dictionary'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['online_dictionary'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['reading_aid'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['reading_aid'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['text_to_speech'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['text_to_speech'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['content_scalling'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['content_scalling'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['textlupe'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['textlupe'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['font_size'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['font_size'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['line_height'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['line_height'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['letter_spacing'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['letter_spacing'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['link_navigator'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['link_navigator'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['highlight_focus'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['highlight_focus'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['mark_hover'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['mark_hover'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['cognitive_reading'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['cognitive_reading'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['high_contrast'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['high_contrast'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['black_white'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_settings']['black_white'],
	'exclude'       => true,
	'inputType'     => 'checkbox',
	'eval'          => array('tl_class' => 'w50'),
];
$GLOBALS['TL_DCA']['tl_settings']['fields']['waveApi'] = [
    'label'		    => &$GLOBALS['TL_LANG']['tl_page']['waveApi'],
	'exclude'       => true,
	'inputType'     => 'text',
	'eval'          => array( 'tl_class' => 'clr', 'tl_class' => 'w50'),
];
?>