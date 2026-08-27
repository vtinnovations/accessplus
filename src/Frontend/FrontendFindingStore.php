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
use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\State\RootScope;

/**
 * Stores frontend (axe) findings DEDUPLICATED across pages: a given rule+selector
 * is one row no matter how many pages it appears on (header/footer/nav repeat
 * everywhere). `occurrences` counts the affected pages.
 *
 * A scan is a session identified by its start time. Each page POSTs its findings
 * during the session; a final call resolves anything not seen this session:
 *   - tstamp >= scanStart  → touched this scan (still present)
 *   - tstamp <  scanStart  → not seen this scan → auto-resolve to Fixed
 * No extra "scan id" column needed — the timestamp is the session marker.
 */
final class FrontendFindingStore
{
    public function __construct(
        private readonly RootScope $rootScope,
    ) {
    }

    /**
     * Upsert one page's findings into the dedup store. Call once per page.
     *
     * @param list<Finding> $findings
     */
    public function ingestPage(int $scanStart, array $findings, string $sampleUrl = '', int $pageId = 0): void
    {
        $now = time();

        // All findings on this page share one root. Prefer the scanned page's own
        // root (reliable even when several roots share a host / have no dns); fall
        // back to matching the URL host. A frontend finding is scoped per root: the
        // SAME rule+selector on two domains (e.g. a shared header) must be two
        // independent rows with their own fix decisions, so we fold the rootId into
        // the stored fingerprint — otherwise scanning domain B would overwrite
        // domain A's row (identical raw fingerprint).
        $rootId = $pageId > 0 ? $this->rootScope->rootIdForPage($pageId) : 0;
        if ($rootId === 0 && $sampleUrl !== '') {
            $rootId = $this->rootScope->rootIdForHost((string) parse_url($sampleUrl, PHP_URL_HOST));
        }

        foreach ($findings as $finding) {
            $fingerprint = $this->scopedFingerprint($finding->fingerprint(), $rootId);
            $model = FindingModel::findOneByFingerprint($fingerprint);

            if ($model === null) {
                $model = new FindingModel();
                $model->createdAt = $now;
                $model->status = FindingStatus::Open->value;
                $model->occurrences = 1;
            } else {
                if ($model->status === FindingStatus::Fixed->value) {
                    $model->status = FindingStatus::Open->value;
                }
                // First touch THIS scan → reset count; subsequent pages → +1.
                $model->occurrences = (int) $model->tstamp >= $scanStart
                    ? (int) $model->occurrences + 1
                    : 1;
            }

            $model->tstamp = $now;
            $model->checkId = $finding->checkId;
            $model->wcagSc = $finding->wcagString();
            $model->severity = $finding->severity->value;
            $model->sourceType = $finding->sourceType->value;
            $model->ptable = $finding->ptable;
            $model->pid = $finding->pid;
            $model->rootId = $rootId;
            $model->field = $finding->field;
            $model->elementLabel = $finding->elementLabel;
            $model->message = $finding->message;
            $model->suggestion = $finding->suggestion;
            // Keep a representative URL for the "show on page" highlight.
            if ($sampleUrl !== '') {
                $model->sampleUrl = $sampleUrl;
            }
            $model->fingerprint = $fingerprint;
            $model->save();
        }
    }

    /**
     * A frontend finding is stored under a root-scoped fingerprint so the same
     * rule+selector on different domains stays separate. rootId 0 keeps the raw
     * fingerprint (single-root install / unresolved host).
     */
    private function scopedFingerprint(string $fingerprint, int $rootId): string
    {
        return $rootId > 0 ? sha1($fingerprint . '|r' . $rootId) : $fingerprint;
    }

    /**
     * Resolve frontend findings not seen during this scan session — but ONLY when
     * the scan actually covered every page. If pages were skipped (cross-origin,
     * X-Frame-Options, timeouts), a finding's absence does NOT mean it was fixed,
     * so we must not auto-resolve — otherwise the "Erledigt" count flaps wildly
     * between runs with false fixes.
     *
     * Scoped to $rootId: a per-domain scan only covers its own root, so it must
     * never auto-resolve another domain's findings. rootId 0 = whole install
     * (single-root / legacy behaviour).
     *
     * @return int Number auto-resolved.
     */
    public function finalizeScan(int $scanStart, bool $fullCoverage = false, int $rootId = 0): int
    {
        if (!$fullCoverage) {
            return 0;
        }

        $columns = ['sourceType = ? AND tstamp < ? AND (status = ? OR status = ?)'];
        $values = ['frontend', $scanStart, FindingStatus::Open->value, FindingStatus::Confirmed->value];
        if ($rootId > 0) {
            $columns[0] .= ' AND rootId = ?';
            $values[] = $rootId;
        }

        $stale = FindingModel::findBy($columns, $values);

        if ($stale === null) {
            return 0;
        }

        $resolved = 0;
        foreach ($stale as $model) {
            $model->status = FindingStatus::Fixed->value;
            $model->save();
            ++$resolved;
        }

        return $resolved;
    }
}
