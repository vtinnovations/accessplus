<?php

/* 
 * @package   [accessplus]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */

/**
 * Namespace
 */
namespace VTInnovations\Accessplus\Hooks;


use Contao\ContentElement;
use Contao\ContentModel;
use Contao\Controller;
use Contao\Input;
use Contao\FilesModel;
 /**
 * Class CustomHooks
 */
class CustomHooks
{
    public function outputFrontendTemplate(string $buffer, string $template): string
    {

        global $objPage;
		
		// Fetch root page data
		$objRootPage = \PageModel::findBy(['type = ?', 'language = ?'], ['root', $objPage->language ]);

        // echo '<pre>'; print_r(); die('</pre>');
        if (strpos($template, 'fe_page') !== false) {
            $addHtmlBody = '
                <div id="accessibility-magnifier"></div> 
            ';
            if($objRootPage->backgroundColor){
                $headerCss  = '
                    <style>
                    .accessibility-toggle-box.access-active{
                        background-color: #' . $objRootPage->backgroundColor . ' !important;
                    }
                    .access-active{
                        background-color: #' . $objRootPage->backgroundColor . ' !important;
                    }
                    .accessibility-action-box:hover { 
                        background-color: #' . $objRootPage->backgroundColor . ' !important;
                    }  
                    </style>
                '; 
            }
            else{
                $headerCss = '';
            }
            

            $buffer = str_replace('</body>',$addHtmlBody,$buffer );
            return str_replace(
				'</head>', 
				'<style id="acc-styler">
					
				 </style>
                 ' . $headerCss . '
				 </head>',
				$buffer
			);
        }

        return $buffer;
    }
   
    public function getSystemMessages(): string{
        $criteriaFile  = ["extension IN ('png', 'jpg', 'jpeg')"];
        $fileCount = FilesModel::countBy($criteriaFile);

        $criteriaGeneratedFile  = ["extension IN ('png', 'jpg', 'jpeg')", "atlPublished=1"];
        $generatedFileCount = FilesModel::countBy($criteriaGeneratedFile);
        
        if($fileCount ==  $generatedFileCount){
            return '
            <p class="tl_info">' .   
                $GLOBALS['TL_LANG']['CTE']['alt_tag_success']
            .'</p>';
        }
        else{
            return '<p class="tl_info">' .
                sprintf($GLOBALS['TL_LANG']['CTE']['alt_tag_generate'], $fileCount, $generatedFileCount)
            .'</p>';
        }
        return '';
    }
}