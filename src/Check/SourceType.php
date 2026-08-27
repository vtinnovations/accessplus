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

namespace VTInnovations\AccessPlus\Check;

/**
 * Where a finding came from. Phase 2 only emits `Database`. The enum exists now
 * so the later HTML/DOM scanner (Frontend) and human audits (Manual) plug into
 * the SAME tl_accessplus_finding table without a schema change.
 */
enum SourceType: string
{
    case Database = 'database';
    case Frontend = 'frontend';
    case Manual   = 'manual';
}
