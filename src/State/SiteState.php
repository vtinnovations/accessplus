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

namespace VTInnovations\AccessPlus\State;

/**
 * The four states a site root can be in. There is no "grace", no "trial" and no
 * "free" member: this product is Pro-only, so anything that is not `Active`
 * means the bundle behaves exactly like it is not installed for that root.
 */
enum SiteState: string
{
    /** No authenticated state stored for this root. */
    case Unlicensed = 'unlicensed';

    /** Authenticated, in-window, domain-matched Pro state. */
    case Active = 'active';

    /** Authentic state whose validity window has ended. No fallback exists. */
    case Expired = 'expired';

    /** Present but not acceptable: tampered, wrong scope, wrong package, stale schema. */
    case Invalid = 'invalid';
}
