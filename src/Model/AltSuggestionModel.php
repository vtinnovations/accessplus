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
 * AI alt-text proposals (tl_accessplus_altsuggestion). Suggestions only — the
 * canonical alt lives in tl_files.meta and is written solely on approval, never
 * by generation (the project guidelines §5). fingerprint = sha1(fileUuid|lang) is unique.
 *
 * @property int         $id
 * @property int         $tstamp
 * @property int         $createdAt
 * @property string      $fileUuid     String UUID of the file.
 * @property string      $filePath     For display only.
 * @property string      $lang
 * @property string|null $suggestion   Proposed alt (plaintext, untrusted).
 * @property string      $decorative   '1' if the model judged it decorative.
 * @property string      $status       pending|approved|rejected|applied
 * @property string      $provider
 * @property string      $model
 * @property string      $fingerprint
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static self|null       findOneByFingerprint($fp, array $options = [])
 * @method static Collection|null findByStatus($status, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class AltSuggestionModel extends Model
{
    protected static $strTable = 'tl_accessplus_altsuggestion';
}
