<?php

/* 
 * @package   [Access Plus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */


/**
 * Namespace
 */
namespace VTInnovations\Accessplus\FeMod;

use Contao\Module;
use Contao\System;
use Contao\BackendTemplate;
use COntao\PageModel;

/**
 * Class Accessibility
 */
class Accessibility extends Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'accessibility';

	/**
	 * Display a wildcard in the back end
	 *
	 * @return string
	 */
	public function generate()
	{
		$requestStack = System::getContainer()->get('request_stack');
    	$request = $requestStack->getCurrentRequest();
		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### Accessibility ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&table=tl_module&act=edit&id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Generate the content
	 */
	protected function compile()
	{
		// Add asset to global array
		$GLOBALS['TL_CSS'][] = 'bundles/accessplus/css/style.css';
		$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/accessplus/js/accessibility.js';
		$arrayAccessibilities = [];
		global $objPage;
		
		// Fetch root page data
		$objRootPage = PageModel::findBy(['type = ?', 'language = ?', 'published = ?'], ['root', $objPage->language, 1 ]);

		//Set Array with accessibility settings
		$accessibilitySettingsArray = [];
		$accessibilitySettingsArray =[
			'enable_accessibility' 				=> \Contao\Config::get('enable_accessibility'),
			'accessibility_title_text' 			=> unserialize(\Contao\Config::get('accessibility_title_text')),
			'accessibility_content_text' 		=> unserialize(\Contao\Config::get('accessibility_content_text')),
			'accessibility_content_background' 	=> unserialize(\Contao\Config::get('accessibility_content_background')),
			'accessibility_link_text' 			=> unserialize(\Contao\Config::get('accessibility_link_text')),
			'accessibility_all_text' 			=> unserialize(\Contao\Config::get('accessibility_all_text')),
			'text_font' 						=> unserialize(\Contao\Config::get('text_font')),
			'alignment' 						=> unserialize(\Contao\Config::get('alignment')),
			'contrast' 							=> unserialize(\Contao\Config::get('contrast')),
			'highlight' 						=> unserialize(\Contao\Config::get('highlight')),
			'big_cursor' 						=> unserialize(\Contao\Config::get('big_cursor')),
			'hide_images' 						=> \Contao\Config::get('hide_images'),
			'animation' 						=> \Contao\Config::get('animation'),
			'mute_sound' 						=> \Contao\Config::get('mute_sound'),
			'epilepsy' 							=> \Contao\Config::get('epilepsy'),
			'visually_impaired' 				=> \Contao\Config::get('visually_impaired'),
			'enable_underline' 					=> \Contao\Config::get('enable_underline'),
			'reading_mask' 						=> \Contao\Config::get('reading_mask'),
			'ADHD_friendly_mode' 				=> \Contao\Config::get('ADHD_friendly_mode'),
			'blindness_mode' 					=> \Contao\Config::get('blindness_mode'),
			'online_dictionary' 				=> \Contao\Config::get('online_dictionary'),
			'reading_aid' 						=> \Contao\Config::get('reading_aid'),
			'text_to_speech' 					=> \Contao\Config::get('text_to_speech'),
			'negative_contrast' 				=> \Contao\Config::get('negative_contrast'),
			'content_scalling' 					=> \Contao\Config::get('content_scalling'),
			'textlupe' 							=> \Contao\Config::get('textlupe'),
			'font_size' 						=> \Contao\Config::get('font_size'),
			'line_height' 						=> \Contao\Config::get('line_height'),
			'letter_spacing' 					=> \Contao\Config::get('letter_spacing'),
			'link_navigator' 					=> \Contao\Config::get('link_navigator'),
			'highlight_focus' 					=> \Contao\Config::get('highlight_focus'),
			'mark_hover' 						=> \Contao\Config::get('mark_hover'),
			'cognitive_reading' 				=> \Contao\Config::get('cognitive_reading'),
			'keyboard_navigation' 				=> \Contao\Config::get('keyboard_navigation'),
			'high_contrast' 				    => \Contao\Config::get('high_contrast'),
			'black_white' 				     	=> \Contao\Config::get('black_white'),
		];
		

		//Accessibilities licence 		 
		$accessibilitiesApiKey = $objRootPage->accessibility_licence;
		$curlAccessibilitiesHeader = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $accessibilitiesApiKey
		];
		$postData = [
			'apiKey' 	=> $accessibilitiesApiKey,
			'url'   	=>  $_SERVER['SERVER_NAME']		
		];

		$accessibilitysCurl = curl_init();
		curl_setopt($accessibilitysCurl, CURLOPT_URL, "https://barrierefreie-internetseite.de/rest/api-v1/validator");
		curl_setopt($accessibilitysCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($accessibilitysCurl, CURLOPT_HTTPHEADER, $curlAccessibilitiesHeader);
		curl_setopt($accessibilitysCurl, CURLOPT_POSTFIELDS, json_encode($postData));
		
		$curlAccessibilitiesReturn = curl_exec($accessibilitysCurl);
		$response  = json_decode($curlAccessibilitiesReturn);
		if (curl_errno($accessibilitysCurl)) {
			echo 'cURL error: ' . curl_error($accessibilitysCurl);
		} 
		else {
			$objRootPageType = PageModel:: findByType('root') ;
			$rootLangsArray = [];
			foreach ($objRootPageType as $key => $value) {
				$rootLangsArray[] = $value->language;
			}

			if($response->status === "SUCCESS"){
				$this->Template->accessibilitiesLicence = $response->status;
				$this->Template->accessibilitySettings = $accessibilitySettingsArray;
				$this->Template->objRootPage = $objRootPage;
			}
			else{
				$this->Template->accessibilitiesLicence = $response->status;
				$this->Template->accessibilitySettings = $accessibilitySettingsArray;
				$this->Template->objRootPage = $objRootPage;
			}
		}
		// Close the cURL session
		curl_close($accessibilitysCurl);
	}
	function xmlToArray($url) {
		// Load the XML file
		$xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
		// Convert the SimpleXMLElement object to a JSON string
		$json = json_encode($xml);
	
		// Convert the JSON string to a PHP associative array
		$array = json_decode($json, true);
	
		// Extract the array of URLs
		$urlArray = $array['url'];
	
		return $urlArray;
	}
}