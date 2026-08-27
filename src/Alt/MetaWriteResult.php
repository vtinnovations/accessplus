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

namespace VTInnovations\AccessPlus\Alt;

/**
 * Outcome of attempting to write an alt text into tl_files.meta.
 *
 *   - Written       → the (empty) slot was filled.
 *   - SkippedManual → a non-empty alt already exists; we never overwrite it
 *                     (the Access+ "$alt . '. ' . $response" bug is the
 *                     anti-pattern). Caller treats the suggestion as obsolete.
 *   - NotFound      → the file row vanished.
 */
enum MetaWriteResult: string
{
    case Written       = 'written';
    case SkippedManual = 'skipped_manual';
    case NotFound      = 'not_found';
}
