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

namespace VTInnovations\AccessPlus\Model;

use Contao\Model;
use Contao\Model\Collection;

/**
 * One full-analysis run (tl_accessplus_run) — the history behind the score trend.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property int    $startedAt
 * @property string $scope          'full' for now.
 * @property int    $score          0–100 heuristic.
 * @property int    $countDone      ✅ fixed total at run time.
 * @property int    $countOneClick  🔘 open with an automated remedy.
 * @property int    $countManual    👤 open needing a human.
 * @property int    $openTotal      oneClick + manual.
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class RunModel extends Model
{
    protected static $strTable = 'tl_accessplus_run';
}
