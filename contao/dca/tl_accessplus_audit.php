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
 * Audit trail. Backs AuditModel; rendered by Controller\AuditModule. Additive.
 */
$GLOBALS['TL_DCA']['tl_accessplus_audit'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,
        'closed'           => true,
        'notEditable'      => true,
        'sql'              => [
            'keys' => [
                'id'     => 'primary',
                'action' => 'index',
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
            'fields' => ['action', 'targetTable', 'field'],
        ],
    ],

    'palettes' => [
        'default' => '{audit_legend},action,targetTable,field,beforeValue,afterValue,userName',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        'action'       => ['filter' => true, 'sql' => "varchar(32) NOT NULL default ''"],
        'targetTable'  => ['sql' => "varchar(64) NOT NULL default ''"],
        'targetUuid'   => ['sql' => "varchar(36) NOT NULL default ''"],
        'targetId'     => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'field'        => ['sql' => "varchar(64) NOT NULL default ''"],
        'lang'         => ['sql' => "varchar(8) NOT NULL default ''"],
        'beforeValue'  => ['sql' => 'text NULL'],
        'beforeAbsent' => ['sql' => "char(1) NOT NULL default ''"],
        'afterValue'   => ['sql' => 'text NULL'],
        'userName'     => ['sql' => "varchar(128) NOT NULL default ''"],
        'undone'       => ['filter' => true, 'sql' => "char(1) NOT NULL default ''"],
    ],
];
