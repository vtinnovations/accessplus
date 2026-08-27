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

use VTInnovations\AccessPlus\Monitor\MonitoringService;

/**
 * Contao cron job (registered as contao.cronjob in services.yaml). Runs on
 * Contao's poor-man's cron (triggered by frontend requests) — no real system
 * cron needed (the project guidelines §2). Throttled inside MonitoringService.
 */
final class MonitorCron
{
    public function __construct(
        private readonly MonitoringService $monitoring,
    ) {
    }

    public function __invoke(string $scope): void
    {
        // Cron path ignores the on-save toggle but stays throttled by interval.
        $this->monitoring->maybeRun(false);
    }
}
