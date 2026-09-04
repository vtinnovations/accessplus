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

namespace VTInnovations\AccessPlus\Cron;

use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteRegistrar;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * The pull half of revocation: a signed online-refresh lease.
 *
 * A server-initiated push can never be guaranteed — an installation may be
 * offline, firewalled, behind changed DNS or deliberately blocking inbound
 * V-T.ONE requests when the authorised domain set changes. So every protected
 * state also carries signed lease policy (`license_refresh_required_at`,
 * `license_grace_until`). Once the refresh deadline passes this job re-confirms
 * the state with V-T.ONE; {@see SiteStatusProvider} fails the protected features
 * closed on its own once `license_grace_until` is reached, whether or not this
 * job ever succeeds.
 *
 * Registered as a contao.cronjob (Contao's poor-man cron, triggered by frontend
 * requests — no system cron needed). A refresh failure here is swallowed: the
 * signed grace window is the enforcement mechanism, not this job's success.
 */
final class LicenseRefreshCron
{
    public function __construct(
        private readonly RootScope $rootScope,
        private readonly SiteStatusProvider $status,
        private readonly SiteRegistrar $registrar,
    ) {
    }

    public function __invoke(string $scope): void
    {
        foreach ($this->rootScope->roots() as $root) {
            $rootId = (int) $root['id'];

            if ($rootId <= 0) {
                continue;
            }

            $status = $this->status->forRoot($rootId);

            // Only act when there is a stored key and the signed lease says a
            // refresh is due. A root that is already withdrawn (revoked) has no
            // lease deadline and is left alone; a lease-expired root keeps being
            // retried so it can recover the moment V-T.ONE is reachable again.
            if (!$status->hasKey() || !$status->isRefreshDue()) {
                continue;
            }

            try {
                $this->registrar->refresh($rootId);
            } catch (\Throwable) {
                // Transient (offline / TLS / 5xx): the previous state is kept and
                // SiteStatusProvider enforces the signed grace cutoff regardless.
            }

            $this->status->forget($rootId);
        }
    }
}
