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
 * Subtitle/transcript jobs (tl_accessplus_track). The WebVTT draft is stored here as
 * editable text; on approval it is written to a real file under files/ and the
 * resulting UUID kept in vttUuid. The source media is never modified.
 *
 * fingerprint = sha1(sourceUuid|lang) is unique (one live track per file+lang).
 *
 * @property int         $id
 * @property int         $tstamp
 * @property int         $createdAt
 * @property string      $sourceUuid  String UUID of the audio/video file.
 * @property string      $sourcePath  For display only.
 * @property string      $lang
 * @property string|null $vtt         WebVTT draft (plaintext, untrusted, editable).
 * @property string      $vttUuid     String UUID of the written .vtt file once applied.
 * @property string      $vttPath     Relative path of the written .vtt (display).
 * @property string      $status      pending|applied|rejected
 * @property string      $provider
 * @property string      $model
 * @property int         $durationMs
 * @property string      $fingerprint
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static self|null       findOneByFingerprint($fp, array $options = [])
 * @method static Collection|null findByStatus($status, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class TrackModel extends Model
{
    protected static $strTable = 'tl_accessplus_track';
}
