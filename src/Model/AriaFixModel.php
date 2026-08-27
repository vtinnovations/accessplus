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
 * Accessible-name fixes (tl_accessplus_ariafix). One row per element that axe
 * reported as missing a name. The editor approves/edits `value`, which is then
 * applied at runtime as an aria-label (only where the element is still unnamed —
 * never overriding an existing name).
 *
 * fingerprint = sha1(selector) is unique (one fix per element selector).
 *
 * @property int         $id
 * @property int         $tstamp
 * @property int         $createdAt
 * @property string      $pageUrl
 * @property string      $selector
 * @property string|null $html
 * @property string      $ruleId
 * @property string      $attribute
 * @property string      $suggestion
 * @property string      $value
 * @property string      $lang
 * @property string      $status       pending|approved|rejected
 * @property string      $fingerprint
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static self|null       findOneByFingerprint($fp, array $options = [])
 * @method static Collection|null findByStatus($status, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class AriaFixModel extends Model
{
    protected static $strTable = 'tl_accessplus_ariafix';
}
