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

use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Runs every registered check and reconciles the results into tl_accessplus_finding.
 *
 * Idempotency (the project guidelines §4) is the whole point of the reconcile:
 *   - a finding seen again keeps its row (and any human Confirmed/Ignored
 *     decision) — only its message/location/timestamp refresh;
 *   - a finding that was Fixed but reappears flips back to Open;
 *   - a stored finding that the scan no longer produces is auto-resolved to
 *     Fixed (never deleted, so history/audit survives).
 *
 * No writes outside this table. Checks themselves never persist.
 */
final class LintRunner
{
    public function __construct(
        private readonly CheckRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly RootScope $rootScope,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    public function run(): RunSummary
    {
        // Scope gate: findings are written per site root, and an unlicensed root
        // must be left exactly as it is — neither new findings nor status changes.
        $licensed = $this->siteStatus->activeRootIds();

        /** @var array<string, Finding> $current fingerprint => finding */
        $current = [];
        $checkIds = [];
        /** @var array<string, true> $failed checks that threw — do not reconcile */
        $failed = [];
        foreach ($this->registry->all() as $check) {
            $checkIds[$check->getId()] = true;

            // A single flaky/slow check (e.g. a heavy DB query that times out) must
            // not abort the whole run nor wipe its findings to "fixed". Isolate it:
            // on failure, skip its reconcile so its existing findings are preserved.
            try {
                foreach ($check->scan() as $finding) {
                    $current[$finding->fingerprint()] = $finding;
                }
            } catch (\Throwable $e) {
                $failed[$check->getId()] = true;
                $this->logger->warning('a11y check failed, preserving its findings', [
                    'check' => $check->getId(),
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        // Reconcile ONLY findings produced by THIS runner's checks (by checkId).
        // Frontend (axe:*) and PDF (pdf_*) findings are owned by their own
        // scanners and must never be auto-resolved here.
        $byFingerprint = [];
        $existing = $this->loadExistingForChecks(array_keys($checkIds));
        foreach ($existing as $model) {
            $byFingerprint[(string) $model->fingerprint] = $model;
        }

        $now = time();
        $created = 0;
        $reopened = 0;
        $resolved = 0;
        $openTotal = 0;
        $bySeverity = [];
        $penalty = 0;
        $seen = [];

        foreach ($current as $fingerprint => $finding) {
            $rootId = $this->rootScope->rootIdForRecord($finding->ptable, $finding->pid);

            if (!$this->mayStore($rootId, $licensed)) {
                continue;
            }

            $seen[$fingerprint] = true;
            $model = $byFingerprint[$fingerprint] ?? null;

            if ($model === null) {
                $model = new FindingModel();
                $model->createdAt = $now;
                $model->status = FindingStatus::Open->value;
                ++$created;
            } elseif ($model->status === FindingStatus::Fixed->value) {
                $model->status = FindingStatus::Open->value;
                ++$reopened;
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
            $model->fingerprint = $fingerprint;
            $model->save();

            // Open + Confirmed count toward the score; Ignored is excluded.
            if (\in_array($model->status, [FindingStatus::Open->value, FindingStatus::Confirmed->value], true)) {
                ++$openTotal;
                $key = $finding->severity->value;
                $bySeverity[$key] = ($bySeverity[$key] ?? 0) + 1;
                $penalty += $finding->severity->weight();
            }
        }

        // Auto-resolve findings that vanished from the current scan — but NEVER
        // for a check that failed this run (its absence proves nothing).
        foreach ($existing as $model) {
            if (isset($failed[(string) $model->checkId])) {
                continue;
            }
            // Never rewrite the status of a finding that belongs to a root we are
            // not licensed for — its absence above only means we did not look.
            if (!$this->mayStore((int) $model->rootId, $licensed)) {
                continue;
            }
            if (!isset($seen[(string) $model->fingerprint])
                && $model->status !== FindingStatus::Fixed->value
            ) {
                $model->status = FindingStatus::Fixed->value;
                $model->tstamp = $now;
                $model->save();
                ++$resolved;
            }
        }

        $score = max(0, 100 - $penalty * 2);

        return new RunSummary($created, $reopened, $resolved, $openTotal, $bySeverity, $score);
    }

    /**
     * A finding may only be written for a licensed site root. Install-wide
     * findings (rootId 0, e.g. file meta) need at least one licensed root.
     *
     * @param list<int> $licensed
     */
    private function mayStore(int $rootId, array $licensed): bool
    {
        return $rootId > 0 ? \in_array($rootId, $licensed, true) : $licensed !== [];
    }

    /**
     * Existing findings whose checkId belongs to this runner's checks.
     *
     * @param list<string> $checkIds
     *
     * @return list<FindingModel>
     */
    private function loadExistingForChecks(array $checkIds): array
    {
        if ($checkIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($checkIds), '?'));
        $collection = FindingModel::findBy(['checkId IN (' . $placeholders . ')'], array_values($checkIds));

        if ($collection === null) {
            return [];
        }

        $out = [];
        foreach ($collection as $model) {
            $out[] = $model;
        }

        return $out;
    }
}
