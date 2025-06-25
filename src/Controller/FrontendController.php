<?php
/**
 * Contao Open Source CMS
 *
 *
 *
 * PHP version 8.2.x
 * @package   Access Plus
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */


/**
 * Namespace
 */
namespace VTInnovations\Accessplus\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontendController
{
    #[Route('/ajax-accessibility', name: FrontendController::class)]
    public function __invoke(Request $request): Response
    {
        // Ensure session is started properly
        if ($request->hasSession()) {
            $session = $request->getSession();
            $arrayAccessibility = $session->get('arrayAccessibility', []);
        } else {
            $arrayAccessibility = [];
        }

        // Reset session if requested
        if ($request->request->get('mode') === 'reset' && $request->request->get('value') == 1) {
            if ($request->hasSession()) {
                $session->set('arrayAccessibility', []);
                $arrayAccessibility = [];
            }
        }

        // Handle Accessibility Toggle
        if ($request->request->get('mode') === 'disableAccessibility' && $request->request->get('value') == 1) {
            setcookie('disableAccessibility', 1, time() + 3600, "/"); // 1-hour expiry
        } else {
            setcookie('disableAccessibility', "", time() - 3600, "/"); // Expire immediately
        }
        // Process Accessibility Changes
        $arrayAccessibility = $this->getAccessibilityArray(
            $request->request->get('mode'),
            $request->request->get('toggle'),
            $request->request->get('value'),
            $arrayAccessibility
        );
        // Store in session if available
        if ($request->hasSession()) {
            $session->set('arrayAccessibility', $arrayAccessibility);
        }

        // Return response instead of using `die()`
        $styleAccessibility = $this->getAccessibilityStyles($arrayAccessibility);
        return new Response($styleAccessibility);
    }

    /**
     * Process accessibility settings based on mode and value.
     */
    protected function getAccessibilityArray($mode, $toggle, $val, $arrayAccessibility){
		switch ($mode){
			case 'alltextcolor':
				if($toggle){
					$arrayAccessibility['alltextcolor'] = $val;
				}
				else{
					unset($arrayAccessibility['alltextcolor']);
				}
				return $arrayAccessibility;
				break;
			case 'textcolor':
				if($toggle){
					$arrayAccessibility['textcolor'] = $val;
				}
				else{
					unset($arrayAccessibility['textcolor']);
				}
				return $arrayAccessibility;
				break;
			case 'titlecolor':
				if($toggle){
					$arrayAccessibility['titlecolor'] = $val;
				}
				else{
					unset($arrayAccessibility['titlecolor']);
				}
				return $arrayAccessibility;
				break;
			case 'linkcolor':
				if($toggle){
					$arrayAccessibility['linkcolor'] = $val;
				}
				else{
					unset($arrayAccessibility['linkcolor']);
				}
				return $arrayAccessibility;
				break;
			case 'bgcolor':
				if($toggle){
					$arrayAccessibility['bgcolor'] = $val;
				}
				else{
					unset($arrayAccessibility['bgcolor']);
				}
				return $arrayAccessibility;
				break;
			case 'font':
				if($toggle){
					$arrayAccessibility['font'] = $val;
				}
				else{
					unset($arrayAccessibility['font']);
				}
				return $arrayAccessibility;
				break;
			case 'alignment':			
				if($toggle){
					$arrayAccessibility['alignment'] = $val;
				}
				else{
					unset($arrayAccessibility['alignment']);
				}
				return $arrayAccessibility;
				break;
			case 'contrast':
				if($toggle){
					$arrayAccessibility['contrast'] = $val;
				}
				else{
					unset($arrayAccessibility['contrast']);
				}
				return $arrayAccessibility;
				break;
			case 'stop-animations':
				if($toggle){
					$arrayAccessibility['stop-animations'] = $val;
				}
				else{
					unset($arrayAccessibility['stop-animations']);
				}
				return $arrayAccessibility;
				break;
			case 'epilepsy':
				if($toggle){
					$arrayAccessibility['epilepsy'] = $val;
				}
				else{
					unset($arrayAccessibility['epilepsy']);
				}
				return $arrayAccessibility;
				break;
			case 'visually-impaired':
				if($toggle){
					$arrayAccessibility['visually-impaired'] = $val;
				}
				else{
					unset($arrayAccessibility['visually-impaired']);
				}
				return $arrayAccessibility;
				break;
			case 'mute-sounds':
				if($toggle){
					$arrayAccessibility['mute-sounds'] = $val;
				}
				else{
					unset($arrayAccessibility['mute-sounds']);
				}
				return $arrayAccessibility;
				break;
			case 'highlight-titles':
				if($toggle){
					$arrayAccessibility['highlight-titles'] = $val;
				}
				else{
					unset($arrayAccessibility['highlight-titles']);
				}
				return $arrayAccessibility;
				break;
			case 'highlight-links':
				if($toggle){
					$arrayAccessibility['highlight-links'] = $val;
				}
				else{
					unset($arrayAccessibility['highlight-links']);
				}
				return $arrayAccessibility;
				break;
			case 'highlight-cursor':
				if($toggle){
					$arrayAccessibility['highlight-cursor'] = $val;
				}
				else{
					unset($arrayAccessibility['highlight-cursor']);
				}
				return $arrayAccessibility;
				break;
			case 'hide-images':
				if($toggle){
					$arrayAccessibility['hide-images'] = $val;
				}
				else{
					unset($arrayAccessibility['hide-images']);
				}
				return $arrayAccessibility;
				break;
			case 'reading-guides':
				if($toggle){
					$arrayAccessibility['reading-guides'] = $val;
				}
				else{
					unset($arrayAccessibility['reading-guides']);
				}
				return $arrayAccessibility;
				break;
			case 'negativer-contrast':
				if($toggle){
					$arrayAccessibility['negativer-contrast'] = $val;
				}
				else{
					unset($arrayAccessibility['negativer-contrast']);
				}
				return $arrayAccessibility;
				break;
			case 'high-contrast':
				if($toggle){
					$arrayAccessibility['high-contrast'] = $val;
				}
				else{
					unset($arrayAccessibility['high-contrast']);
				}
				return $arrayAccessibility;
				break;
			default:
				break;
		}
	}
    /**
     * Generate CSS styles based on accessibility settings.
     */
    protected function getAccessibilityStyles($arrayAccessibility)
    {
        $arrayTagStyles = [];
        $arrayStyles = [];
        $arrayTitles = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $arrayTexts = ['span', 'p', 'li', 'a', 'label', 'input', 'select', 'textarea', 'legend', 'code', 'pre'];
        $alignTags = ['div', 'section', 'article', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'a', 'li', 'footer', 'header'];
        $arrayTags = ['span', 'a', 'p', 'li', 'i', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'input', 'label', 'strong', 'textarea'];
		$arrayFonts = ['span', 'a', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'input', 'label', 'strong', 'textarea', 'button', 'select', 'small', 'em', 'b', 'code', 'blockquote', 'caption', 'legend', 'th', 'td', 'div'];
        $epilepsyTags = ['span', 'a', 'p', 'li', 'i', 'h1', 'img' , 'h2', 'h3', 'h4', 'h5', 'h6', 'input', 'label', 'strong', 'textarea'];
        $arraylinks = ['a', 'link', 'p a', 'li a', 'span a'];
        $arraytest = ['header'];
        $arrayCursors = ['a', 'button', 'select', 'input', 'textarea'];
		$arrayhighContrasts = ['html'];

        foreach ($arrayAccessibility as $key => $value) {
            switch ($key) {
                case 'alltextcolor':
                    foreach ($arrayTags as $tag) {
                        $arrayTagStyles[$tag][] = "color: {$value} !important";
                    }
                    break;
                case 'textcolor':
                    foreach ($arrayTexts as $tag) {
                        $arrayTagStyles[$tag][] = "color: {$value} !important";
                    }
                    break;
                case 'titlecolor':
                    foreach ($arrayTitles as $tag) {
                        $arrayTagStyles[$tag][] = "color: {$value} !important";
                    }
                    break;
                case 'linkcolor':
                    foreach ($arraylinks as $tag) {
                        $arrayTagStyles[$tag][] = "color: {$value} !important";
                    }
                    break;
                case 'bgcolor':
                    foreach ($arrayTags as $tag) {
                        $arrayTagStyles[$tag][] = "background-color: {$value} !important";
                    }
                    break;
                case 'contrast':
                    foreach ($arrayTags as $tag) {
                        $contrastStyle = ($value === 'dark') 
                            ? "color: #fff !important; background-color: #000 !important"
                            : "color: #000 !important; background-color: #fff !important";
                        $arrayTagStyles[$tag][] = $contrastStyle;
                    }
                    break;
                case 'stop-animations':
                    foreach ($alignTags as $tag) {
                        $arrayTagStyles[$tag][] = "animation: none !important; transition: none !important";
                    }
                    break;
                case 'epilepsy':
                    foreach ($arraytest as $tag) {
                        $arrayTagStyles[$tag][] = "filter: grayscale(1)";
                    }
                    break;
				case 'visually-impaired':
					foreach($epilepsyTags as $epilepsyTag){
						$arrayTagStyles[$epilepsyTag][] = "zoom:1.05";
					}					
					break;
				case 'reading-guides':
					$arrayTagStyles['body'][] = "--readabler-reading-guide-width: 500px; --readabler-reading-guide-height: 12px; --readabler-reading-guide-radius: 10px; --readabler-reading-guide-bg: rgba(0, 0, 0, 1); --readabler-reading-guide-border-color: rgba(0, 0, 0, 1); --readabler-reading-guide-border-width: 2px; --readabler-reading-guide-arrow: 10px; --readabler-reading-guide-arrow-margin: -10px;";					
					break;
                case 'negativer-contrast':
                    $arrayTagStyles['body > div, body > header, body > section, body > article, body > footer'][] = "filter: invert(1)";
                    $arrayTagStyles['body > #accessibility-popup-box'][] = "filter: none";
                    break;
                case 'highlight-titles':
                    foreach ($arrayTitles as $tag) {
                        $arrayTagStyles[$tag][] = "outline: 2px solid rgba(0, 0, 0, 1) !important; outline-offset: 2px !important";
                    }
                    break;
                case 'highlight-links':
                    foreach ($arraylinks as $tag) {
                        $arrayTagStyles[$tag][] = "outline: 2px solid rgba(0, 0, 0, 1) !important; outline-offset: 2px !important";
                    }
                    break;
				case 'font':
					foreach($arrayFonts as $arrayFont){
						$arrayTagStyles[$arrayFont][] = "font-family: " . $value . ' !important';
					}					
					break;
				case 'alignment':
					foreach($alignTags as $alignTag){
						$arrayTagStyles[$alignTag][] = "text-align: " .$value . ' !important';
					}		
				case 'high-contrast':
					foreach($arrayhighContrasts as $arrayhighContrast){
						$arrayTagStyles[$arrayhighContrast][] = "filter: contrast(135%)!important; -webkit-backdrop-filter: sepia(1)!important; backdrop-filter: sepia(1)!important";
					}			
					break;
            }
        }

        // Convert styles to CSS
        foreach ($arrayTagStyles as $tag => $definition) {
            $arrayStyles[] = "{$tag} { " . implode('; ', $definition) . " }";
        }

        return implode("\n", $arrayStyles);
    }
}


?>

