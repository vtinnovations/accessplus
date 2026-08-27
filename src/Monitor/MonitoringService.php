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

namespace VTInnovations\AccessPlus\Monitor;

use VTInnovations\AccessPlus\Dashboard\FullAnalysis;
use VTInnovations\AccessPlus\Model\RunModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Drives periodic re-scans of the DATABASE checks. Three triggers, no hard cron
 * dependency (the project guidelines §2): a CLI command, a Contao poor-man's cron job, and an
 * onsubmit hook after content edits — all funnel through here.
 *
 * The onsubmit/cron paths are THROTTLED via last_monitor_at so a burst of edits
 * (or many frontend requests under poor-man's cron) cannot re-scan in a loop.
 *
 * Note: the frontend (axe) scan is browser-only and is NOT part of automated
 * monitoring; this covers the DB linter + run/score snapshot.
 */
final class MonitoringService
{
    public function __construct(
        private readonly FullAnalysis $fullAnalysis,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    /**
     * Run if the throttle window has elapsed. When $respectToggle is true the
     * on-save switch is honoured (used by the onsubmit hook).
     *
     * @return RunModel|null The run, or null if skipped.
     */
    public function maybeRun(bool $respectToggle = true): ?RunModel
    {
        // Scope gate: automated re-scans (cron, on-save hook) need at least one
        // licensed site root; the linter itself still skips unlicensed roots.
        if (!$this->siteStatus->hasAnyActive()) {
            return null;
        }

        if ($respectToggle && !(bool) $this->runtimeConfig->get('monitor_on_save', true)) {
            return null;
        }

        $interval = max(30, (int) $this->runtimeConfig->get('monitor_interval', 120));
        $last = (int) $this->runtimeConfig->get('last_monitor_at', 0);

        if (time() - $last < $interval) {
            return null;
        }

        return $this->run();
    }

    /**
     * Force a re-scan now and stamp the throttle clock.
     */
    public function run(): RunModel
    {
        $run = $this->fullAnalysis->run();
        $this->runtimeConfig->set('last_monitor_at', time());

        return $run;
    }
}
