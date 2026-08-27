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
 * Plain/Easy-language drafts table. Rendered by Controller\SimplifyModule; this
 * DCA exists to create/migrate the table and back SimplificationModel. Additive,
 * no foreign tables touched.
 */
$GLOBALS['TL_DCA']['tl_accessplus_simplification'] = [

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
                'pageId'      => 'index',
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
            'fields' => ['pageId', 'register', 'lang', 'status'],
        ],
    ],

    'palettes' => [
        'default' => '{simple_legend},register,lang,draft,status',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        'pageId'      => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'contentId'   => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'snippetKey'  => ['sql' => "varchar(40) NOT NULL default 'main'"],
        'register'    => ['filter' => true, 'sql' => "varchar(16) NOT NULL default 'einfach'"],
        'lang'        => ['filter' => true, 'sql' => "varchar(8) NOT NULL default ''"],
        'sourceText'  => ['sql' => 'mediumtext NULL'],
        'draft'       => ['sql' => 'mediumtext NULL'],
        'sourceHash'  => ['sql' => "varchar(40) NOT NULL default ''"],
        'status'      => ['filter' => true, 'sql' => "varchar(16) NOT NULL default 'pending'"],
        'locked'      => ['filter' => true, 'sql' => "char(1) NOT NULL default ''"],
        'provider'    => ['sql' => "varchar(32) NOT NULL default ''"],
        'model'       => ['sql' => "varchar(64) NOT NULL default ''"],
        'fingerprint' => ['sql' => "varchar(40) NOT NULL default ''"],
    ],
];
