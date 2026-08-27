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
 * One persisted accessibility finding (tl_accessplus_finding). Written by the
 * LintRunner, read by the backend report. `fingerprint` is unique and makes
 * re-scans idempotent.
 *
 * @property int         $id
 * @property int         $tstamp        Last seen.
 * @property int         $createdAt     First detected.
 * @property string      $checkId
 * @property string      $wcagSc        Comma-separated WCAG success criteria.
 * @property string      $severity      critical|serious|moderate|minor
 * @property string      $sourceType    database|frontend|manual
 * @property string      $ptable        Source table (e.g. tl_content).
 * @property int         $pid           Source row id.
 * @property string      $field         Source field (e.g. alt).
 * @property string      $elementLabel  Human "where" pointer.
 * @property string|null $message
 * @property string|null $suggestion
 * @property string      $status        open|confirmed|ignored|fixed
 * @property int         $occurrences   Affected pages/elements for a deduped finding.
 * @property string      $sampleUrl     Representative frontend URL for highlight.
 * @property string      $fingerprint   sha1 identity of the issue location.
 *
 * @method static self|null         findById($id, array $options = [])
 * @method static self|null         findOneByFingerprint($fp, array $options = [])
 * @method static Collection|null   findByStatus($status, array $options = [])
 * @method static Collection|null   findAll(array $options = [])
 * @method static int               countBy($column, $value, array $options = [])
 */
class FindingModel extends Model
{
    protected static $strTable = 'tl_accessplus_finding';
}
