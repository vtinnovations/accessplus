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

$GLOBALS['TL_DCA']['tl_layout']['config']['onsubmit_callback'][] = function(\Contao\DataContainer $dc) {
    if(\Contao\Input::get('act') == 'edit' && $dc->activeRecord->id){
        // Get the current record
        $layout = \Contao\LayoutModel::findByPk($dc->activeRecord->id);
        if ($layout === null) {
            return;
        }
        else{
            if(!$layout->addJQuery){
                
                $database = \Contao\Database::getInstance();

                $sql = "SELECT m.* 
                        FROM tl_module m 
                        JOIN tl_layout l ON l.id = ?
                        WHERE m.type = 'accessibility' 
                        AND (
                            l.modules LIKE CONCAT('%\"mod\";s:', LENGTH(m.id), ':\"', m.id, '\"%')
                            OR l.modules LIKE CONCAT('%\"mod\";i:', m.id, ';%')
                        )";

                $result = $database->prepare($sql)->execute($dc->activeRecord->id);

                if ($result->numRows > 0) {
                    \Contao\Message::addError($GLOBALS['TL_LANG']['CTE']['alert_tl_layout']);
                    \Contao\Controller::redirect(\Contao\Backend::addToUrl('act=edit'));
                }
                
            }
        }
    }
    



     
    
};