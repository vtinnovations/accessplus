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
 * Findings table. The bundle renders its own report UI (Controller\ReportModule),
 * so this DCA exists mainly to (a) create/migrate the table via contao:migrate
 * and (b) back the FindingModel. A minimal read-only list is provided as a
 * fallback browser. The table is created additively — no foreign tables touched.
 */
$GLOBALS['TL_DCA']['tl_accessplus_finding'] = [

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
                'severity'    => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => 1, // sorted (integer literal — DataContainer::MODE_* constants are not in 4.13)
            'fields'      => ['severity', 'tstamp DESC'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['severity', 'checkId', 'elementLabel'],
        ],
    ],

    'palettes' => [
        'default' => '{finding_legend},checkId,severity,status,message,suggestion,elementLabel',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        'checkId' => [
            'filter' => true,
            'search' => true,
            'sql'    => "varchar(64) NOT NULL default ''",
        ],
        'wcagSc' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'severity' => [
            'filter' => true,
            'sql'    => "varchar(16) NOT NULL default 'moderate'",
        ],
        'sourceType' => [
            'filter' => true,
            'sql'    => "varchar(16) NOT NULL default 'database'",
        ],
        'ptable' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'pid' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        // Site root (tl_page type='root') this finding belongs to (Modell 2
        // per-domain scoping). 0 = install-wide / shared (e.g. tl_files).
        'rootId' => [
            'filter' => true,
            'sql'    => 'int(10) unsigned NOT NULL default 0',
        ],
        'field' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'elementLabel' => [
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'message'    => ['sql' => 'text NULL'],
        'suggestion' => ['sql' => 'text NULL'],
        'status' => [
            'filter' => true,
            'sql'    => "varchar(16) NOT NULL default 'open'",
        ],
        'occurrences' => [
            // How many pages/elements this (deduplicated) finding affects.
            'sql' => 'int(10) unsigned NOT NULL default 1',
        ],
        'sampleUrl' => [
            // Representative frontend URL for the "show on page" highlight.
            'sql' => "varchar(2048) NOT NULL default ''",
        ],
        'fingerprint' => [
            'sql' => "varchar(40) NOT NULL default ''",
        ],
    ],
];
