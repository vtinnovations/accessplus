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
 * Append-only audit trail of applied fixes (tl_accessplus_audit). Each row captures
 * enough to UNDO: the before value and whether the field was absent, plus the
 * after value used as a no-clobber guard.
 *
 * @property int         $id
 * @property int         $tstamp
 * @property int         $createdAt
 * @property string      $action       e.g. 'alt_apply'
 * @property string      $targetTable
 * @property string      $targetUuid   String UUID for tl_files targets ('' otherwise).
 * @property int         $targetId     Row id for int-keyed targets (0 otherwise).
 * @property string      $field
 * @property string      $lang
 * @property string|null $beforeValue
 * @property string      $beforeAbsent '1' if the field key was absent before.
 * @property string|null $afterValue
 * @property string      $userName
 * @property string      $undone       '1' once reverted.
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static Collection|null findByAction($action, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class AuditModel extends Model
{
    protected static $strTable = 'tl_accessplus_audit';
}
