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

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Once-per-authenticated-backend-session marker, scoped to one site root.
 *
 * Used by the module-entry signal so that reloads, tab switches, Turbo
 * navigations, Ajax calls and parallel browser tabs of the SAME backend session
 * produce exactly one event per root — while a new login may produce one again.
 *
 * Why the server session and not something else:
 *   - a process-static boolean would be per PHP worker, not per session;
 *   - a database flag would be permanent and never reset on logout;
 *   - browser storage or a JavaScript cookie would be client-controlled.
 *
 * Atomicity: PHP holds an exclusive lock on the session for the duration of a
 * request, so the read-modify-write below is serialized against parallel tabs of
 * the same session. The marker stores root ids only — never the key, the domain,
 * the session token or a digest of any of them.
 */
final class BackendSessionClaim
{
    private const ATTRIBUTE = 'accessplus_scope_entry';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Claims the marker. Returns true exactly once per session and root.
     */
    public function claim(int $rootId): bool
    {
        if ($rootId <= 0) {
            return false;
        }

        try {
            $session = $this->requestStack->getSession();

            if (!$session->isStarted()) {
                // Only an already authenticated backend session may claim; do not
                // start one just to send a signal.
                return false;
            }

            $claimed = $session->get(self::ATTRIBUTE, []);
            $claimed = \is_array($claimed) ? $claimed : [];

            if (isset($claimed[$rootId])) {
                return false;
            }

            $claimed[$rootId] = true;
            $session->set(self::ATTRIBUTE, $claimed);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Drops the marker for one root, so a re-activation inside the same session
     * signals again. Called when the state of that root is removed.
     */
    public function release(int $rootId): void
    {
        try {
            $session = $this->requestStack->getSession();

            if (!$session->isStarted()) {
                return;
            }

            $claimed = $session->get(self::ATTRIBUTE, []);

            if (\is_array($claimed) && isset($claimed[$rootId])) {
                unset($claimed[$rootId]);
                $session->set(self::ATTRIBUTE, $claimed);
            }
        } catch (\Throwable) {
            // No session, nothing to release.
        }
    }
}
