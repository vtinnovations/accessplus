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
use VTInnovations\AccessPlus\State\RegistrationStore;

/**
 * Atomicity and rollback of the authoritative store.
 */
final class RegistrationStoreTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/accessplus-store-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    public function testBytesSurviveTheRoundTripExactly(): void
    {
        $store = new RegistrationStore($this->projectDir);
        $bytes = "{\"a\":1}\n  trailing whitespace matters ";

        $store->commit(5, $bytes, ['license_version' => 3], static fn () => null);
        $read = $store->read(5);

        self::assertNotNull($read);
        self::assertSame($bytes, $read['bytes']);
        self::assertSame(3, $read['envelope']['license_version']);
    }

    public function testAVerifierVetoLeavesThePreviousStateIntact(): void
    {
        $store = new RegistrationStore($this->projectDir);
        $store->commit(5, '{"v":1}', ['license_version' => 1], static fn () => null);

        try {
            $store->commit(5, '{"v":2}', ['license_version' => 2], static function (): void {
                throw new \RuntimeException('nope');
            });
            self::fail('The commit should have been vetoed.');
        } catch (\Throwable) {
            // expected
        }

        $read = $store->read(5);
        self::assertNotNull($read);
        self::assertSame('{"v":1}', $read['bytes'], 'A rejected replacement must not destroy valid state.');
    }

    public function testFailureAfterActivationRollsBack(): void
    {
        $store = new RegistrationStore($this->projectDir);
        $store->commit(5, '{"v":1}', ['license_version' => 1], static fn () => null);

        $calls = 0;
        try {
            // Passes for the staged copy, fails for the activated one.
            $store->commit(5, '{"v":2}', ['license_version' => 2], static function () use (&$calls): void {
                if (++$calls >= 3) {
                    throw new \RuntimeException('post-activation check failed');
                }
            });
            self::fail('The commit should have rolled back.');
        } catch (\Throwable) {
            // expected
        }

        $read = $store->read(5);
        self::assertNotNull($read);
        self::assertSame('{"v":1}', $read['bytes']);
    }

    public function testRemoveClearsOnlyTheGivenRoot(): void
    {
        $store = new RegistrationStore($this->projectDir);
        $store->commit(5, '{"v":1}', ['license_version' => 1], static fn () => null);
        $store->commit(6, '{"v":1}', ['license_version' => 1], static fn () => null);

        $store->remove(5);

        self::assertNull($store->read(5));
        self::assertNotNull($store->read(6));
    }

    public function testStateLivesInThePrivateStateDirectory(): void
    {
        (new RegistrationStore($this->projectDir))->commit(5, '{"v":1}', ['license_version' => 1], static fn () => null);

        self::assertFileExists($this->projectDir . '/var/accessplus/roots/5/state.json');
    }

    public function testCorruptContainerReadsAsAbsentRatherThanThrowing(): void
    {
        $store = new RegistrationStore($this->projectDir);
        $store->commit(5, '{"v":1}', ['license_version' => 1], static fn () => null);

        file_put_contents($this->projectDir . '/var/accessplus/roots/5/state.json', 'not json');

        self::assertNull($store->read(5));
    }
}
