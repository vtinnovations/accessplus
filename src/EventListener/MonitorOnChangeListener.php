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

namespace VTInnovations\AccessPlus\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Monitor\MonitoringService;

/**
 * Re-scans (throttled) after a content/page/form-field is saved in the backend,
 * so findings stay fresh without a hard cron (the project guidelines §2). Registered via
 * #[AsCallback] config.onsubmit — verified available on Contao 4.13+.
 *
 * Wrapped in try/catch: a monitoring hiccup must NEVER block the editor's save.
 */
final class MonitorOnChangeListener
{
    public function __construct(
        private readonly MonitoringService $monitoring,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsCallback(table: 'tl_content', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_page', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_form_field', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        try {
            $this->monitoring->maybeRun(true);
        } catch (\Throwable $e) {
            $this->logger->warning('a11y monitor onsubmit skipped', ['reason' => $e->getMessage()]);
        }
    }
}
