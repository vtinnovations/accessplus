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

namespace VTInnovations\AccessPlus\Exchange;

use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Collects the signals of one invocation and sends them after the response has
 * been handed to the client, so no administrator or visitor ever waits for them.
 *
 * The module-entry event is queued only by the authoritative licence section
 * after it has atomically claimed the session marker, which is why a transport
 * failure here must never be retried inside the same session — the claim is
 * already spent, by design.
 */
final class DeferredSignals
{
    /** @var list<array{domain: string, key: string}> */
    private array $moduleEntries = [];

    private bool $flushed = false;

    public function __construct(
        private readonly UsageSignal $signal,
        private readonly SiteStatusProvider $status,
    ) {
    }

    /**
     * Queued by the licence section after a successful session claim. The key
     * stays inside the server process; it is never rendered or logged.
     */
    public function queueModuleEntry(string $domain, string $licenseKey): void
    {
        if ($domain === '' || $licenseKey === '') {
            return;
        }

        $this->moduleEntries[] = ['domain' => $domain, 'key' => $licenseKey];
    }

    /**
     * Sends at most one invocation event plus any queued module-entry events.
     */
    public function flush(): void
    {
        if ($this->flushed) {
            return;
        }

        $this->flushed = true;

        foreach ($this->moduleEntries as $entry) {
            $this->signal->moduleEntry($entry['domain'], $entry['key']);
        }

        $this->moduleEntries = [];

        $domain = $this->status->invocationDomain();

        if ($domain !== null && $domain !== '') {
            $this->signal->invocation($domain);
        }
    }
}
