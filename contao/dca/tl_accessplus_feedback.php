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
use VTInnovations\AccessPlus\Controller\FeedbackInboxGate;

/*
 * Feedback channel entries. A real DCA-backed backend module (do=accessplus_feedback)
 * so editors get list/filter/view/status-edit/delete for free. Only the status
 * is editable; the reporter's text is read-only.
 */
$GLOBALS['TL_DCA']['tl_accessplus_feedback'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,
        'closed'           => true,   // no "new" button — entries arrive via the frontend
        // Scope gate: without a licensed site root the inbox becomes read-only
        // and says so. Existing reports are never hidden or deleted — losing an
        // accessibility complaint would be the wrong kind of enforcement.
        'onload_callback'  => [[FeedbackInboxGate::class, 'onLoad']],
        'sql'              => [
            'keys' => [
                'id'     => 'primary',
                'status' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => 2,
            'fields'      => ['tstamp DESC'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['status', 'email', 'url'],
            'format' => '[%s] %s — %s',
        ],
        'operations' => [
            'edit'   => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
            'show'   => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],

    'palettes' => [
        'default' => '{report_legend},name,email,url,message;{status_legend},status',
    ],

    'fields' => [
        'id'        => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'    => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'createdAt' => ['sql' => 'int(10) unsigned NOT NULL default 0'],

        'name'    => ['eval' => ['readonly' => true], 'sql' => "varchar(128) NOT NULL default ''"],
        'email'   => ['eval' => ['readonly' => true], 'sql' => "varchar(255) NOT NULL default ''"],
        'url'     => ['eval' => ['readonly' => true], 'sql' => "varchar(2048) NOT NULL default ''"],
        'message' => ['eval' => ['readonly' => true], 'inputType' => 'textarea', 'sql' => 'text NULL'],

        'status' => [
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['new', 'progress', 'done'],
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default 'new'",
        ],
    ],
];
