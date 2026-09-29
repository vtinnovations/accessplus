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

namespace VTInnovations\AccessPlus\Tests\State;

use PHPUnit\Framework\TestCase;
use VTInnovations\AccessPlus\State\UsageLedger;

/**
 * Covers the authoritative Demo-quota accounting: persistence across separate
 * instances (standing in for "survives logout/session-expiry/cache-clear",
 * since none of those touch this file), atomic cap enforcement, and the
 * reserve-up-to primitive the axe-issue truncation relies on.
 *
 * True concurrent-process races are not exercised here (PHPUnit is
 * single-process); {@see testSequentialReservationsNeverExceedTheCap} instead
 * proves the invariant the lock is meant to preserve — the running total never
 * goes over the cap no matter how many reservations are attempted — and is the
 * property a real race could only violate if the locked critical section in
 * {@see UsageLedger::reserveUpTo()} were removed.
 */
final class UsageLedgerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/accessplus-ledger-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    public function testConsumingUnderTheCapSucceeds(): void
    {
        $ledger = new UsageLedger($this->projectDir);

        self::assertTrue($ledger->tryConsume(5, 'alt_images', 25));
        self::assertSame(1, $ledger->used(5, 'alt_images'));
        self::assertSame(24, $ledger->remaining(5, 'alt_images', 25));
    }

    public function testConsumingAtTheCapFails(): void
    {
        $ledger = new UsageLedger($this->projectDir);

        for ($i = 0; $i < 25; ++$i) {
            self::assertTrue($ledger->tryConsume(5, 'alt_images', 25), "unit $i should still fit under the cap");
        }

        self::assertFalse($ledger->tryConsume(5, 'alt_images', 25), 'the 26th unit must be refused');
        self::assertSame(25, $ledger->used(5, 'alt_images'), 'a refused reservation must not move the counter');
    }

    public function testAFailedReservationWritesNothing(): void
    {
        $ledger = new UsageLedger($this->projectDir);

        $ledger->tryConsume(5, 'pages_scanned', 15, 15); // exhaust in one call
        self::assertSame(15, $ledger->used(5, 'pages_scanned'));

        self::assertFalse($ledger->tryConsume(5, 'pages_scanned', 15));
        self::assertSame(15, $ledger->used(5, 'pages_scanned'));
    }

    public function testSequentialReservationsNeverExceedTheCap(): void
    {
        $ledger = new UsageLedger($this->projectDir);
        $granted = 0;

        // Simulate many overlapping small batches (e.g. concurrent scans) each
        // trying to reserve 4 units against a cap of 15. Whatever the request
        // pattern, the running total must never exceed the cap.
        foreach ([4, 4, 4, 4, 4, 4] as $requested) {
            $granted += $ledger->reserveUpTo(5, 'pages_scanned', 15, $requested);
            self::assertLessThanOrEqual(15, $ledger->used(5, 'pages_scanned'));
        }

        self::assertSame(15, $granted, 'the cap truncates the last partial batch rather than rejecting it outright');
        self::assertSame(15, $ledger->used(5, 'pages_scanned'));
    }

    public function testReserveUpToGrantsAPartialAmountAndNoMore(): void
    {
        // Generic mechanism test only — the app does not currently persist any
        // bucket named 'widgets'; the 30-issue axe cap is enforced per-result
        // by FrontendScanController without going through the ledger at all
        // (see its class docblock), since the spec's persisted-counter
        // requirement covers only alt_images and pages_scanned.
        $ledger = new UsageLedger($this->projectDir);

        $ledger->tryConsume(5, 'widgets', 30, 28);
        self::assertSame(2, $ledger->remaining(5, 'widgets', 30));

        $granted = $ledger->reserveUpTo(5, 'widgets', 30, 10);

        self::assertSame(2, $granted);
        self::assertSame(30, $ledger->used(5, 'widgets'));
        self::assertSame(0, $ledger->reserveUpTo(5, 'widgets', 30, 1), 'fully spent — nothing more is ever granted');
    }

    public function testBucketsAreIndependent(): void
    {
        $ledger = new UsageLedger($this->projectDir);

        $ledger->tryConsume(5, 'alt_images', 25, 25);

        self::assertSame(25, $ledger->used(5, 'alt_images'));
        self::assertSame(0, $ledger->used(5, 'pages_scanned'), 'ALT-image usage must never leak into the page-scan bucket');
        self::assertTrue($ledger->tryConsume(5, 'pages_scanned', 15));
    }

    public function testRootsAreIndependent(): void
    {
        $ledger = new UsageLedger($this->projectDir);

        $ledger->tryConsume(5, 'pages_scanned', 15, 15);

        self::assertSame(0, $ledger->used(6, 'pages_scanned'), 'one root exhausting its allowance must not affect another root');
        self::assertTrue($ledger->tryConsume(6, 'pages_scanned', 15));
    }

    /**
     * The counter is keyed by root id alone — a fresh {@see UsageLedger}
     * instance (standing in for a new request, a new session, or the licence
     * having just been refreshed/re-entered) reads the SAME persisted total.
     */
    public function testUsageSurvivesAcrossSeparateInstancesAndIsNotResetByAnythingButConsumption(): void
    {
        $first = new UsageLedger($this->projectDir);
        $first->tryConsume(5, 'alt_images', 25, 9);

        $second = new UsageLedger($this->projectDir);
        self::assertSame(9, $second->used(5, 'alt_images'), 'a new instance/session/request must see the same authoritative total');

        $third = new UsageLedger($this->projectDir);
        self::assertSame(9, $third->used(5, 'alt_images'), 'merely reading — or re-instantiating the ledger — must never itself consume or reset anything');
    }

    public function testInstallWideScopeZeroIsValid(): void
    {
        // Root id 0 is the deliberate install-wide scope used for a feature
        // (ALT generation) that is not bound to a single site root — it must
        // behave exactly like any other scope, not be silently rejected.
        $ledger = new UsageLedger($this->projectDir);

        self::assertTrue($ledger->tryConsume(0, 'alt_images', 25));
        self::assertSame(1, $ledger->used(0, 'alt_images'));
    }

    public function testInvalidInputsGrantNothing(): void
    {
        $ledger = new UsageLedger($this->projectDir);

        self::assertFalse($ledger->tryConsume(-1, 'alt_images', 25), 'a negative root id is not a valid scope');
        self::assertSame(0, $ledger->reserveUpTo(5, 'alt_images', 25, 0));
        self::assertSame(0, $ledger->reserveUpTo(5, 'alt_images', 0, 5), 'a non-positive cap grants nothing');
    }
}
