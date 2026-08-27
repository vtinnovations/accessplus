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

namespace VTInnovations\AccessPlus\Frontend;

use VTInnovations\AccessPlus\Check\Finding;
use VTInnovations\AccessPlus\Check\Severity;
use VTInnovations\AccessPlus\Check\SourceType;

/**
 * Maps an axe-core result (the `violations` array, as posted from the browser)
 * into our Finding value objects with sourceType=frontend. Pure — no DB, no
 * request — so the mapping is unit testable.
 *
 * One finding per offending node so each element is individually actionable;
 * capped per rule to avoid pathological pages exploding the table (the cap is
 * surfaced, never silent).
 */
final class AxeResultMapper
{
    private const NODES_PER_RULE_CAP = 25;

    /**
     * @param list<array<string, mixed>> $violations Decoded axe `result.violations`.
     *
     * @return list<Finding>
     */
    public function map(int $pageId, string $pageTitle, array $violations): array
    {
        $findings = [];

        foreach ($violations as $violation) {
            if (!\is_array($violation)) {
                continue;
            }

            $ruleId = (string) ($violation['id'] ?? '');
            if ($ruleId === '') {
                continue;
            }

            $help = (string) ($violation['help'] ?? $violation['description'] ?? $ruleId);
            $helpUrl = (string) ($violation['helpUrl'] ?? '');
            $wcag = $this->wcagFromTags(\is_array($violation['tags'] ?? null) ? $violation['tags'] : []);
            $ruleImpact = (string) ($violation['impact'] ?? '');

            $nodes = \is_array($violation['nodes'] ?? null) ? $violation['nodes'] : [];
            $count = 0;

            foreach ($nodes as $node) {
                if (++$count > self::NODES_PER_RULE_CAP || !\is_array($node)) {
                    break;
                }

                $selector = $this->selector($node['target'] ?? null);
                $impact = (string) ($node['impact'] ?? $ruleImpact);

                // No WCAG tag → axe "best practice" (e.g. landmark-one-main,
                // region, page-has-heading-one). Helpful, but NOT a WCAG/BFSG
                // conformance failure — cap at Minor so the mandatory WCAG
                // issues (contrast, alt, labels) clearly sort above. The empty
                // wcagCriteria lets the UI badge it "Best Practice".
                $isBestPractice = $wcag === [];
                $severity = $isBestPractice ? Severity::Minor : $this->severity($impact);

                // axe's per-node failureSummary is the concrete, human-readable
                // reason ("Fix any of the following: …"). Far more helpful than
                // the rule name — store it as the suggestion, plus the help link.
                $suggestion = trim((string) ($node['failureSummary'] ?? ''));
                if ($helpUrl !== '') {
                    $suggestion .= ($suggestion !== '' ? "\n" : '') . 'Mehr: ' . $helpUrl;
                }

                // Page-INDEPENDENT identity: same rule + same selector collapses
                // to ONE finding across all pages (header/footer/nav repeat on
                // every page). pid=0, ptable='frontend' so the fingerprint
                // (checkId|ptable|pid|field) carries no page — dedup is automatic
                // on upsert; the affected-page count is tracked in occurrences.
                $findings[] = new Finding(
                    checkId: 'axe:' . $ruleId,
                    wcagCriteria: $wcag,
                    severity: $severity,
                    sourceType: SourceType::Frontend,
                    ptable: 'frontend',
                    pid: 0,
                    field: $ruleId . '|' . substr(sha1($selector), 0, 16),
                    elementLabel: $selector !== '' ? $selector : '(Dokument)',
                    message: $help,
                    suggestion: $suggestion,
                );
            }
        }

        return $findings;
    }

    private function severity(string $impact): Severity
    {
        return match (strtolower($impact)) {
            'critical' => Severity::Critical,
            'serious' => Severity::Serious,
            'minor' => Severity::Minor,
            default => Severity::Moderate,
        };
    }

    /**
     * axe `target` is a list of selectors (and may contain nested arrays for
     * shadow DOM). Reduce to a single readable selector string.
     *
     * @param mixed $target
     */
    private function selector($target): string
    {
        if (\is_string($target)) {
            return $target;
        }

        if (!\is_array($target)) {
            return '';
        }

        $parts = [];
        foreach ($target as $part) {
            if (\is_string($part)) {
                $parts[] = $part;
            } elseif (\is_array($part)) {
                foreach ($part as $sub) {
                    if (\is_string($sub)) {
                        $parts[] = $sub;
                    }
                }
            }
        }

        return mb_substr(implode(' ', $parts), 0, 200);
    }

    /**
     * Extract WCAG success criteria from axe tags ("wcag111" → "1.1.1").
     *
     * @param list<mixed> $tags
     *
     * @return list<string>
     */
    private function wcagFromTags(array $tags): array
    {
        $out = [];
        foreach ($tags as $tag) {
            if (\is_string($tag) && preg_match('/^wcag(\d)(\d)(\d+)$/', $tag, $m) === 1) {
                $out[$m[1] . '.' . $m[2] . '.' . $m[3]] = $m[1] . '.' . $m[2] . '.' . $m[3];
            }
        }

        return array_values($out);
    }
}
