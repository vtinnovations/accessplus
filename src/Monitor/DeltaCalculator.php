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

namespace VTInnovations\AccessPlus\Monitor;

use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\Model\RunModel;

/**
 * Computes the delta between the two most recent runs: new findings detected and
 * findings resolved in the window since the previous run, plus the score change.
 */
final class DeltaCalculator
{
    public function latest(): Delta
    {
        $runs = RunModel::findAll(['order' => 'startedAt DESC', 'limit' => 2]);
        if ($runs === null || $runs->count() === 0) {
            return new Delta(false, 0, 0, 0);
        }

        $models = [];
        foreach ($runs as $run) {
            $models[] = $run;
        }

        $latest = $models[0];
        $previous = $models[1] ?? null;

        if (!$previous instanceof RunModel) {
            return new Delta(false, 0, 0, 0);
        }

        $since = (int) $previous->startedAt;

        // New = first detected after the previous run and still open/confirmed.
        $newCount = (int) FindingModel::countBy(
            ['createdAt >= ? AND (status = ? OR status = ?)'],
            [$since, 'open', 'confirmed'],
        );

        // Resolved = flipped to fixed in the window since the previous run.
        $resolvedCount = (int) FindingModel::countBy(
            ['status = ? AND tstamp >= ?'],
            ['fixed', $since],
        );

        return new Delta(
            true,
            $newCount,
            $resolvedCount,
            (int) $latest->score - (int) $previous->score,
        );
    }
}
