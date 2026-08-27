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
 * Run history. Backs RunModel; the dashboard renders its own view. Additive.
 */
$GLOBALS['TL_DCA']['tl_accessplus_run'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,
        'closed'           => true,
        'notEditable'      => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => 1,
            'fields'      => ['startedAt DESC'],
            'panelLayout' => 'limit',
        ],
        'label' => [
            'fields' => ['startedAt', 'score'],
        ],
    ],

    'palettes' => [
        'default' => '{run_legend},scope,score,countDone,countOneClick,countManual',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'startedAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'scope'     => ['sql' => "varchar(32) NOT NULL default 'full'"],
        'rootId'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'score'     => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'countDone'     => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'countOneClick' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'countManual'   => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'openTotal'     => ['sql' => 'int(10) unsigned NOT NULL default 0'],
    ],
];
