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
 * Flags website root pages that have no language attribute set (WCAG 3.1.1).
 * The root page's `language` drives the document `<html lang>`, so an empty one
 * means the whole tree ships without a declared language.
 */
final class PageLanguageCheck extends AbstractDatabaseCheck
{
    public function getId(): string
    {
        return 'page_language_missing';
    }

    public function getLabel(): string
    {
        return Text::get('check.page_language_missing');
    }

    public function scan(): iterable
    {
        if (!$this->tableExists('tl_page') || !$this->columnExists('tl_page', 'language')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, dns FROM tl_page
             WHERE type = ? AND (language IS NULL OR language = ?)',
            ['root', ''],
        );

        foreach ($rows as $row) {
            $pageId = (int) $row['id'];
            $title = (string) ($row['title'] ?? '');
            $dns = (string) ($row['dns'] ?? '');

            yield new Finding(
                checkId: $this->getId(),
                wcagCriteria: ['3.1.1'],
                severity: Severity::Serious,
                sourceType: SourceType::Database,
                ptable: 'tl_page',
                pid: $pageId,
                field: 'language',
                elementLabel: 'Startpunkt "' . ($title !== '' ? $title : (string) $pageId) . '"' . ($dns !== '' ? ' (' . $dns . ')' : ''),
                message: 'Website-Startpunkt ohne gesetzte Sprache.',
                suggestion: 'Im Startpunkt die Sprache setzen (z. B. "de"), damit <html lang> korrekt ist.',
            );
        }
    }
}
