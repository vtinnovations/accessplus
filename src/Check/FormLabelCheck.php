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

namespace VTInnovations\AccessPlus\Check;

use VTInnovations\AccessPlus\I18n\Text;

/**
 * Flags input form fields without a label (WCAG 1.3.1 / 3.3.2 / 4.1.2). Only
 * field types that actually need a visible label are considered; structural and
 * action types (html, headline, submit, …) are excluded.
 *
 * Gracefully skips when tl_form_field is absent (form bundle not installed).
 */
final class FormLabelCheck extends AbstractDatabaseCheck
{
    /** Input types that require a label. */
    private const LABELLED_TYPES = [
        'text', 'password', 'textarea', 'select', 'radio', 'checkbox',
        'upload', 'captcha', 'range', 'email', 'url', 'number', 'tel',
        'date', 'time', 'phone',
    ];

    public function getId(): string
    {
        return 'form_field_no_label';
    }

    public function getLabel(): string
    {
        return Text::get('check.form_field_no_label');
    }

    public function scan(): iterable
    {
        if (!$this->tableExists('tl_form_field')) {
            return;
        }

        $hasForm = $this->tableExists('tl_form');

        // Positional placeholders for the IN() list — avoids the DBAL array-type
        // constant, which differs across DBAL 2/3/4 (Contao 4.13 vs 5.x).
        $placeholders = implode(',', array_fill(0, \count(self::LABELLED_TYPES), '?'));

        $sql = 'SELECT ff.id AS fieldId, ff.type AS type, ff.name AS name'
            . ($hasForm ? ', f.title AS formTitle' : '')
            . ' FROM tl_form_field ff'
            . ($hasForm ? ' LEFT JOIN tl_form f ON f.id = ff.pid' : '')
            . ' WHERE ff.type IN (' . $placeholders . ') AND (ff.label IS NULL OR ff.label = ?)';

        $params = array_values(self::LABELLED_TYPES);
        $params[] = '';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        foreach ($rows as $row) {
            $fieldId = (int) $row['fieldId'];
            $name = (string) ($row['name'] ?? '');
            $formTitle = (string) ($row['formTitle'] ?? '');

            $where = $formTitle !== '' ? 'Formular "' . $formTitle . '" › ' : '';

            yield new Finding(
                checkId: $this->getId(),
                wcagCriteria: ['1.3.1', '3.3.2'],
                severity: Severity::Serious,
                sourceType: SourceType::Database,
                ptable: 'tl_form_field',
                pid: $fieldId,
                field: 'label',
                elementLabel: $where . 'Feld "' . ($name !== '' ? $name : (string) $fieldId) . '" (' . (string) $row['type'] . ')',
                message: sprintf('Formularfeld vom Typ "%s" ohne Beschriftung (label).', (string) $row['type']),
                suggestion: 'Jedem Eingabefeld ein sprechendes Label geben (nicht nur Placeholder).',
            );
        }
    }
}
