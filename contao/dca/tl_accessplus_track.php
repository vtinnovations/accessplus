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
 * Subtitle/transcript jobs table. Rendered by Controller\SubtitleModule; this
 * DCA exists to create/migrate the table and back TrackModel. Additive, no
 * foreign tables touched.
 */
$GLOBALS['TL_DCA']['tl_accessplus_track'] = [

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
            'fields' => ['sourcePath', 'lang', 'status'],
        ],
    ],

    'palettes' => [
        'default' => '{track_legend},sourcePath,lang,vtt,status',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        'sourceUuid'  => ['sql' => "varchar(36) NOT NULL default ''"],
        'sourcePath'  => ['search' => true, 'sql' => "varchar(255) NOT NULL default ''"],
        'lang'        => ['filter' => true, 'sql' => "varchar(8) NOT NULL default ''"],
        'vtt'         => ['sql' => 'mediumtext NULL'],
        'vttUuid'     => ['sql' => "varchar(36) NOT NULL default ''"],
        'vttPath'     => ['sql' => "varchar(255) NOT NULL default ''"],
        'status'      => ['filter' => true, 'sql' => "varchar(16) NOT NULL default 'pending'"],
        'provider'    => ['sql' => "varchar(32) NOT NULL default ''"],
        'model'       => ['sql' => "varchar(64) NOT NULL default ''"],
        'durationMs'  => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'fingerprint' => ['sql' => "varchar(40) NOT NULL default ''"],
    ],
];
