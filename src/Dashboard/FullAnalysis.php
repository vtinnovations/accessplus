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

namespace VTInnovations\AccessPlus\Dashboard;

use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\Check\LintRunner;
use VTInnovations\AccessPlus\Check\Severity;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\Model\RunModel;

/**
 * "Einmal durchlaufen": runs every check, then snapshots the result into the run
 * history (tl_accessplus_run) with the 3-column counts and the score.
 *
 * It does NOT generate AI content or apply fixes — purely detection + bookkeeping
 * (scope discipline: those are explicit, separate actions).
 */
final class FullAnalysis
{
    public function __construct(
        private readonly LintRunner $lintRunner,
        private readonly CategoryClassifier $classifier,
    ) {
    }

    /**
     * @param int $rootId Restrict counts + score to one site root (Modell 2).
     *                    0 = whole install. The linter always scans everything;
     *                    only the snapshot counts are scoped.
     */
    public function run(int $rootId = 0): RunModel
    {
        $this->lintRunner->run();

        $oneClick = 0;
        $manual = 0;

        // Frontend (axe) findings are excluded here — the 3-column run counts
        // reflect the deterministic database analysis only (frontend flaps with
        // scan coverage and lives in the report).
        $open = FindingModel::findBy(
            ...$this->scoped('(status = ? OR status = ?) AND sourceType != ?', [FindingStatus::Open->value, FindingStatus::Confirmed->value, 'frontend'], $rootId),
        );
        if ($open !== null) {
            foreach ($open as $finding) {
                $category = $this->classifier->classify((string) $finding->checkId, (string) $finding->status);
                if ($category === Category::OneClick) {
                    ++$oneClick;
                } else {
                    ++$manual;
                }
            }
        }

        // Frontend (axe) findings flap with scan coverage — exclude them so the
        // "done" count reflects only genuinely resolved DB/manual findings.
        $doneCollection = FindingModel::findBy(
            ...$this->scoped('status = ? AND sourceType != ?', [FindingStatus::Fixed->value, 'frontend'], $rootId),
        );
        $done = $doneCollection !== null ? $doneCollection->count() : 0;

        // Honest score over ALL open findings (DB + frontend), severity-weighted
        // (critical 4 / serious 3 / moderate 2 / minor 1). 100 − Σ, clamped.
        $penalty = 0;
        $allOpen = FindingModel::findBy(
            ...$this->scoped('(status = ? OR status = ?)', [FindingStatus::Open->value, FindingStatus::Confirmed->value], $rootId),
        );
        if ($allOpen !== null) {
            foreach ($allOpen as $finding) {
                $penalty += Severity::tryFrom((string) $finding->severity)?->weight() ?? 0;
            }
        }
        $score = max(0, min(100, 100 - $penalty));

        $now = time();
        $run = new RunModel();
        $run->tstamp = $now;
        $run->startedAt = $now;
        $run->scope = 'full';
        $run->rootId = $rootId;
        $run->score = $score;
        $run->countDone = $done;
        $run->countOneClick = $oneClick;
        $run->countManual = $manual;
        $run->openTotal = $oneClick + $manual;
        $run->save();

        return $run;
    }

    /**
     * Append an `AND rootId = ?` clause when scoping to a single root.
     *
     * @param list<mixed> $values
     *
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function scoped(string $where, array $values, int $rootId): array
    {
        if ($rootId > 0) {
            $where .= ' AND rootId = ?';
            $values[] = $rootId;
        }

        return [[$where], $values];
    }
}
