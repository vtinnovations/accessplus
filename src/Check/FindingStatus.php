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
 * Lifecycle of a finding.
 *
 *   - Open      → freshly detected, not yet triaged.
 *   - Confirmed → a human agreed it is a real problem to fix.
 *   - Ignored   → a human decided it is a non-issue / accepted.
 *   - Fixed     → no longer detected on the latest scan (auto-resolved).
 *
 * User-set states (Confirmed/Ignored) are sticky across re-scans; the runner
 * only flips a finding to Fixed when it disappears from the source.
 */
enum FindingStatus: string
{
    case Open      = 'open';
    case Confirmed = 'confirmed';
    case Ignored   = 'ignored';
    case Fixed     = 'fixed';
}
