<?php 
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2019 - 2014 V&T Innovations LLP
 *
 * PHP version 8.2.x
 * @package   accessplus
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
use Contao\PageModel;
use Contao\FilesModel;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;

#[Route('/update-alt-tags', name: AltTagsController::class)]
class AltTagsController
{
    private ContaoFrameworkInterface $framework;

    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }

    public function __invoke(Request $request): Response
    {
        // Initialize the Contao framework
        $this->framework->initialize();

        $objRootPageType = PageModel::findBy(['type = ?', 'published = ?'], ['root', 1]) ;
        
        $rootLangsArray = [];

		foreach ($objRootPageType as $key => $value) {
            $rootLangsArray[] = [
                'lang'          => $value->language,
                'openApiKey'    => $value->openApiKey
            ];
		}
        
        $criteria = ["extension IN ('png', 'jpg', 'jpeg')", "atlPublished=0"];

        // Find the files with a limit of 5
        $objFiles = FilesModel::findBy($criteria, null, ['limit' => 10]);
        
        
        if($objFiles){
            foreach($objFiles as $objFile){
                // Add asset to global array
                $arrayTempMataData = [];
                //Fetch image data
                $objImgage = FilesModel::findById($objFile->id);
                                
                foreach($rootLangsArray as $rootLangArray){
                    if($rootLangArray['openApiKey']){
                        // Your OpenAI API key
                        $api_key = $rootLangArray['openApiKey'];

                        // The URL to the OpenAI API endpoint
                        $url = 'https://api.openai.com/v1/chat/completions';
                        $image_content = base64_encode(file_get_contents($objFile->path));
                
                        // The request payload
                        $data = [
                            'model' => 'gpt-4o',
                            'messages' => [
                                [
                                    'role' => 'user',
                                    'content' => [
                                        ['type' => 'text', 'text' => "What’s in this image in " . $rootLangArray['lang'] . " ? . I want this in max 125 character"],
                                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $image_content, 'detail' => 'high']],
                                    ],
                                ]
                            ],
                            'max_tokens' => 300,
                        ];
                        
                        // Initialize cURL
                        $ch = curl_init($url);

                        // Set the cURL options
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $api_key,
                        ]);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                        // Execute the request and get the response
                        $response = curl_exec($ch);
            
                        //Check for cURL errors
                        if (curl_errno($ch)) {
                            echo 'cURL error: ' . curl_error($ch);
                        } 
                        else {
                            // Decode the JSON response
                            $response_data = json_decode($response, true);

                            // Print the response
                            $response_content = $response_data['choices'][0]['message']['content'];                            
                        }

                        // Close the cURL session
                        curl_close($ch);

                        //Fetch Image meta data
                        $objMetaData = unserialize($objImgage->meta);
                        //Set Data in  arrayTempMataData
                        if($objMetaData[$rootLangArray['lang']]){
                            $arrayTempMataData[$rootLangArray['lang']] = [
                                'title'     => $objMetaData[$rootLangArray['lang']]['title'],
                                'alt'       => $objMetaData[$rootLangArray['lang']]['alt']. '. ' . $response_content,
                                'link'      => $objMetaData[$rootLangArray['lang']]['link'],
                                'caption'   => $objMetaData[$rootLangArray['lang']]['caption'],
                                'license'   => $objMetaData[$rootLangArray['lang']]['license'],
                            ];
                        }
                        else{
                            $arrayTempMataData[$rootLangArray['lang']] = [
                                'title'     => null,
                                'alt'       => $response_content,
                                'link'      => null,
                                'caption'   => null,
                                'license'   => null,
                            ];
                        }
                    }
                }
                //Update Files with meta data 
                $objImgage->meta 			=  serialize($arrayTempMataData);
                $objImgage->atlPublished 	=  1;
                $objImgage->save();
                
            }
            $massege = "Image Alt Generate DONE";
        }

        return new Response('Image Alt Generate DONE');
    }
}

?>
