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
 * Accessible-name (ARIA label) fixes. Populated from the frontend scan for
 * elements that axe reported as missing a name (link-name, button-name,
 * frame-title, aria-input-field-name). Rendered by Controller\AriaModule; applied
 * at runtime by EventListener\AriaInjector. Additive table, no foreign tables
 * touched.
 */
$GLOBALS['TL_DCA']['tl_accessplus_ariafix'] = [

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
            'fields' => ['selector', 'ruleId', 'status'],
        ],
    ],

    'palettes' => [
        'default' => '{aria_legend},selector,ruleId,suggestion,value,status',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        // Where axe found it (for "show on page" + AI context).
        'pageUrl'    => ['sql' => "varchar(2048) NOT NULL default ''"],
        // CSS selector to target the element at runtime.
        'selector'   => ['search' => true, 'sql' => "varchar(512) NOT NULL default ''"],
        // Short outer-HTML snippet of the element (preview + AI context, escaped on output).
        'html'       => ['sql' => 'text NULL'],
        // axe rule that flagged it (link-name, button-name, frame-title, …).
        'ruleId'     => ['filter' => true, 'sql' => "varchar(64) NOT NULL default ''"],
        // Attribute we set — aria-label for MVP.
        'attribute'  => ['sql' => "varchar(32) NOT NULL default 'aria-label'"],
        // Heuristic name proposed during the scan (prefill).
        'suggestion' => ['sql' => "varchar(255) NOT NULL default ''"],
        // Editor-approved value that actually gets applied.
        'value'      => ['sql' => "varchar(255) NOT NULL default ''"],
        'lang'       => ['sql' => "varchar(8) NOT NULL default ''"],
        // pending|approved|rejected
        'status'     => ['filter' => true, 'sql' => "varchar(16) NOT NULL default 'pending'"],
        'fingerprint' => ['sql' => "varchar(40) NOT NULL default ''"],
    ],
];
