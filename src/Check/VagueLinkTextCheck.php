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

use VTInnovations\AccessPlus\Check\Analysis\VagueLinkText;
use VTInnovations\AccessPlus\I18n\Text;

/**
 * Flags non-descriptive link text — both dedicated hyperlink elements
 * (linkTitle) and inline anchors inside rich-text fields (WCAG 2.4.4).
 *
 * Inline-anchor extraction is a plain regex over the STORED markup; no
 * rendering, no DOM of a live page.
 */
final class VagueLinkTextCheck extends AbstractDatabaseCheck
{
    public function getId(): string
    {
        return 'link_text_vague';
    }

    public function getLabel(): string
    {
        return Text::get('check.link_text_vague');
    }

    public function scan(): iterable
    {
        if (!$this->tableExists('tl_content')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.id AS contentId, c.type AS type, c.linkTitle AS linkTitle, c.text AS text,
                    a.title AS articleTitle, p.title AS pageTitle
             FROM tl_content c
             LEFT JOIN tl_article a ON a.id = c.pid AND c.ptable = :artTable
             LEFT JOIN tl_page p ON p.id = a.pid
             WHERE c.type = :hyperlink OR c.text LIKE :anchor",
            ['artTable' => 'tl_article', 'hyperlink' => 'hyperlink', 'anchor' => '%<a %'],
        );

        foreach ($rows as $row) {
            $contentId = (int) $row['contentId'];

            // 1) Dedicated hyperlink element.
            if (($row['type'] ?? '') === 'hyperlink') {
                $linkTitle = (string) ($row['linkTitle'] ?? '');
                if (VagueLinkText::isVague($linkTitle)) {
                    yield $this->makeFinding($row, $contentId, 'linkTitle', $linkTitle);
                }

                continue;
            }

            // 2) Inline anchors inside rich text.
            $text = (string) ($row['text'] ?? '');
            foreach ($this->vagueAnchorLabels($text) as $label) {
                yield $this->makeFinding($row, $contentId, 'text', $label);

                break; // one finding per element is enough to action it
            }
        }
    }

    /**
     * @return list<string>
     */
    private function vagueAnchorLabels(string $html): array
    {
        if ($html === '' || stripos($html, '<a') === false) {
            return [];
        }

        $out = [];
        if (preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $html, $matches) !== false) {
            foreach ($matches[1] as $inner) {
                if (VagueLinkText::isVague((string) $inner)) {
                    $out[] = VagueLinkText::normalize((string) $inner);
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function makeFinding(array $row, int $contentId, string $field, string $label): Finding
    {
        return new Finding(
            checkId: $this->getId(),
            wcagCriteria: ['2.4.4'],
            severity: Severity::Moderate,
            sourceType: SourceType::Database,
            ptable: 'tl_content',
            pid: $contentId,
            field: $field,
            elementLabel: $this->locationLabel($row, 'Link-Element', $contentId),
            message: sprintf('Linktext "%s" beschreibt das Ziel nicht.', trim($label)),
            suggestion: 'Linktext so formulieren, dass er das Ziel auch ohne Kontext beschreibt.',
        );
    }
}
