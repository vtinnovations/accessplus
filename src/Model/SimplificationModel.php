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
 * Plain/Easy-language drafts (tl_accessplus_simplification). One row per
 * content element + register + language. The simplified text is stored here and
 * swapped into the frontend ONLY when status=approved — the original tl_content
 * is never modified.
 *
 * fingerprint = sha1(contentId|register|lang) is unique. sourceHash lets the
 * monitor flag drafts whose source text changed (stale).
 *
 * @property int         $id
 * @property int         $tstamp
 * @property int         $createdAt
 * @property int         $pageId
 * @property int         $contentId
 * @property string      $snippetKey   Stable key of the text snippet within the element.
 * @property string      $register     einfach|leicht
 * @property string      $lang
 * @property string|null $sourceText   Original snippet text (for in-page substitution).
 * @property string|null $draft        Simplified HTML (sanitised, untrusted).
 * @property string      $sourceHash   Hash of the source text when generated.
 * @property string      $status       pending|approved|rejected
 * @property string      $locked       '1' = pinned: never regenerated, stays live.
 * @property string      $provider
 * @property string      $model
 * @property string      $fingerprint
 *
 * @method static self|null       findById($id, array $options = [])
 * @method static self|null       findOneByFingerprint($fp, array $options = [])
 * @method static Collection|null findByStatus($status, array $options = [])
 * @method static Collection|null findBy($column, $value, array $options = [])
 * @method static Collection|null findAll(array $options = [])
 */
class SimplificationModel extends Model
{
    protected static $strTable = 'tl_accessplus_simplification';
}
