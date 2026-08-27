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
 * Barrier reports submitted via the frontend feedback channel
 * (tl_accessplus_feedback). Required by BFSG: users must be able to report
 * accessibility barriers. Content is user-supplied → plaintext, escaped on
 * display.
 *
 * @property int         $id
 * @property int         $tstamp
 * @property int         $createdAt
 * @property string      $name
 * @property string      $email
 * @property string      $url
 * @property string|null $message
 * @property string      $status   new|progress|done
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static Collection|null findByStatus($status, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class FeedbackModel extends Model
{
    protected static $strTable = 'tl_accessplus_feedback';
}
