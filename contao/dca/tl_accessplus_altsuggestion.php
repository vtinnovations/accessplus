<?php

declare(strict_types=1);

/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

use Contao\DC_Table;

/*
 * Alt-text proposals table. Rendered by Controller\AltModule; this DCA exists to
 * create/migrate the table and back AltSuggestionModel. Additive, no foreign
 * tables touched.
 */
$GLOBALS['TL_DCA']['tl_accessplus_altsuggestion'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,
        'closed'           => true,
        'notEditable'      => true,
        'sql'              => [
            'keys' => [
                'id'          => 'primary',
                'fingerprint' => 'unique',
                'status'      => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => 1,
            'fields'      => ['tstamp DESC'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['filePath', 'lang', 'status'],
        ],
    ],

    'palettes' => [
        'default' => '{alt_legend},filePath,lang,suggestion,decorative,status',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        'fileUuid'   => ['sql' => "varchar(36) NOT NULL default ''"],
        'filePath'   => ['search' => true, 'sql' => "varchar(255) NOT NULL default ''"],
        'lang'       => ['filter' => true, 'sql' => "varchar(8) NOT NULL default ''"],
        'suggestion' => ['sql' => 'text NULL'],
        'decorative' => ['sql' => "char(1) NOT NULL default ''"],
        'status'     => ['filter' => true, 'sql' => "varchar(16) NOT NULL default 'pending'"],
        'provider'   => ['sql' => "varchar(32) NOT NULL default ''"],
        'model'      => ['sql' => "varchar(64) NOT NULL default ''"],
        'fingerprint' => ['sql' => "varchar(40) NOT NULL default ''"],
    ],
];
