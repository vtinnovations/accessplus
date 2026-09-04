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
 * The states a site root can be in. There is no "grace", no "trial" and no
 * "free" member: this product is Pro-only, so anything that is not `Active`
 * means the bundle behaves exactly like it is not installed for that root.
 *
 * `Revoked` and `Expired` are AUTHENTIC negative states — the issuer signed
 * them and the client applied them. They are not failures; they are the answer
 * to "what may this installation do now?" (nothing), which is a different
 * question from "did V-T.ONE issue this state?" (yes). `Invalid` is the failure
 * case: a record that never authenticated at all.
 */
enum SiteState: string
{
    /** No authenticated state stored for this root. */
    case Unlicensed = 'unlicensed';

    /** Authenticated, in-window, domain-matched Pro state. */
    case Active = 'active';

    /** Authentic state whose validity window has ended. No fallback exists. */
    case Expired = 'expired';

    /** Authentic state that withdraws entitlement (explicit signed revocation). */
    case Revoked = 'revoked';

    /** Present but not acceptable: tampered, wrong scope, wrong package, stale schema. */
    case Invalid = 'invalid';
}
