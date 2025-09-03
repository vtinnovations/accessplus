<?php
/**
 * @package   [Accessplus]
 * @author    V&T Innovations
 * @license   GNU/LGPL
 * @copyright V&T Innovations 2025 - 2030
 */

namespace VTInnovations\Accessplus\Backend;

use Contao\BackendModule;
use Contao\BackendTemplate;
use Contao\System;
use Contao\Input;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class AccessbilityAnalysis extends BackendModule 
{
    protected $strTemplate = 'accessibility_analysis'; 
    private ?HttpClientInterface $httpClient = null;
 
    public function __construct()
    {
        parent::__construct();
    }
    
    private function getHttpClient(): HttpClientInterface
    {
        if ($this->httpClient === null) {
            $container = System::getContainer();
            
            // Try different service names that might be available
            $serviceNames = ['http_client', 'Symfony\Contracts\HttpClient\HttpClientInterface'];
            
            foreach ($serviceNames as $serviceName) {
                if ($container->has($serviceName)) {
                    $this->httpClient = $container->get($serviceName);
                    break;
                }
            }
            
            // If still null, create a default client
            if ($this->httpClient === null) {
                $this->httpClient = HttpClient::create();
            }
        }
        return $this->httpClient;
    }
 
    
    protected function compile()
    {
        // Add public assets to global list
        $GLOBALS['TL_CSS'][]        = 'bundles/accessplus/css/backend-accessibility-analysis.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/accessplus/js/backend-accessibility-analysis.js';

        // Fetch root page data
		$objRootPage = PageModel::findBy(['type = ?', 'published = ?'], ['root', 1 ]);
        


        //Request Token
        $requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
        $apiResponse = null;
        $errorMessage = null;
        $clientUrl = null;
        
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST'){
            if(Input::post('root-page')){ 
                if ($workshopPage = PageModel::findByPk(Input::post('root-page'))) {
                    $workshopPageUrl = $workshopPage->getAbsoluteUrl();
                    if($workshopPageUrl){
                        $apiKey = \Contao\Config::get('waveApi');
                        if($apiKey){
                            try {
                                $clientUrl = $workshopPageUrl;
                                $apiUrl = 'https://wave.webaim.org/api/request?key='. $apiKey.'&reporttype=4&url='.$clientUrl;
                                $headers = [
                                    'Accept' => 'application/json',
                                ];
                
                                // Send API request
                                $response = $this->getHttpClient()->request('POST', $apiUrl, [
                                    'headers' => $headers,
                                ]);
                
                                // Convert JSON response to an array
                                $apiResponse = $response->toArray();
                            } catch (\Exception $e) {
                                $errorMessage = $e->getMessage();
                            }
                        }
                        else{
                          $errorMessage =  $GLOBALS['TL_LANG']['wave']['wave_key_not_found']; 
                        }
                        
                    }
                }
            }
        }
        // Assigning template vars
        $this->Template->objRootPages    = $objRootPage;
        $this->Template->requestToken   = $requestToken;
        $this->Template->apiResponse     = $apiResponse;
        $this->Template->errorMessage    = $errorMessage;
        $this->Template->clientUrl       = $clientUrl;
        $this->Template->waveCategory    = $GLOBALS['TL_LANG']['wave']['category'];
    }
   
}
