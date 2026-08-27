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

use Symfony\Component\HttpKernel\Event\TerminateEvent;
use VTInnovations\AccessPlus\Exchange\DeferredSignals;

/**
 * Flushes the queued signals after the response has been sent (kernel.terminate),
 * so a slow or unreachable endpoint can never delay a page, and never surface as
 * an error to a visitor or an editor.
 *
 * Registered in services.yaml rather than via an attribute, because the bundle
 * supports Symfony 5.4 where #[AsEventListener] does not exist yet.
 */
final class UsageSignalListener
{
    public function __construct(
        private readonly DeferredSignals $signals,
    ) {
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        try {
            $this->signals->flush();
        } catch (\Throwable) {
            // A signal must never influence anything.
        }
    }
}
