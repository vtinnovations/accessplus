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

use Contao\StringUtil;
use VTInnovations\AccessPlus\Check\Analysis\HeadingHierarchy;
use VTInnovations\AccessPlus\I18n\Text;

/**
 * Detects skipped heading levels and multiple H1s within a single article, from
 * the headline content elements in document order (WCAG 1.3.1 / 2.4.6).
 *
 * Only headings expressed as content elements are visible to the DB; headings
 * baked into custom templates are a job for the later HTML scanner.
 */
final class HeadingHierarchyCheck extends AbstractDatabaseCheck
{
    public function getId(): string
    {
        return 'heading_hierarchy';
    }

    public function getLabel(): string
    {
        return Text::get('check.heading_hierarchy');
    }

    public function scan(): iterable
    {
        if (!$this->tableExists('tl_content')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.id AS contentId, c.pid AS articleId, c.headline AS headline,
                    a.title AS articleTitle, p.title AS pageTitle
             FROM tl_content c
             LEFT JOIN tl_article a ON a.id = c.pid AND c.ptable = :artTable
             LEFT JOIN tl_page p ON p.id = a.pid
             WHERE c.type = :headline
             ORDER BY c.pid ASC, c.sorting ASC",
            ['artTable' => 'tl_article', 'headline' => 'headline'],
        );

        // Group ordered headings per article.
        $byArticle = [];
        foreach ($rows as $row) {
            $articleId = (int) $row['articleId'];
            $level = $this->levelFromHeadline(\is_string($row['headline'] ?? null) ? $row['headline'] : null);
            if ($level === null) {
                continue;
            }

            $byArticle[$articleId][] = ['row' => $row, 'level' => $level];
        }

        foreach ($byArticle as $headings) {
            $levels = [];
            foreach ($headings as $index => $heading) {
                $levels[] = ['index' => $index, 'level' => $heading['level']];
            }

            foreach (HeadingHierarchy::analyze($levels) as $issue) {
                $heading = $headings[$issue['index']];
                $row = $heading['row'];
                $contentId = (int) $row['contentId'];

                $message = $issue['problem'] === 'multiple_h1'
                    ? sprintf('Mehrere H1 im selben Artikel (Element #%d ist ein weiteres H1).', $contentId)
                    : sprintf('Überschriftensprung auf h%d (vorherige Ebene übersprungen).', $issue['level']);

                yield new Finding(
                    checkId: $this->getId(),
                    wcagCriteria: ['1.3.1', '2.4.6'],
                    severity: Severity::Moderate,
                    sourceType: SourceType::Database,
                    ptable: 'tl_content',
                    pid: $contentId,
                    field: 'headline',
                    elementLabel: $this->locationLabel($row, 'Überschrift', $contentId, 'h' . $issue['level']),
                    message: $message,
                    suggestion: 'Überschriftenebenen lückenlos und mit genau einem H1 je Seite strukturieren.',
                );
            }
        }
    }

    private function levelFromHeadline(?string $serialized): ?int
    {
        if ($serialized === null || $serialized === '') {
            return null;
        }

        $headline = StringUtil::deserialize($serialized);
        $unit = \is_array($headline) ? (string) ($headline['unit'] ?? '') : '';

        if (preg_match('/^h([1-6])$/', $unit, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }
}
