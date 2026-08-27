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

namespace VTInnovations\AccessPlus\Tests\Security;

use PHPUnit\Framework\TestCase;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Security\TrustAnchors;

/**
 * Release gate: a build whose pinned ring is empty, placeholder-only or
 * structurally broken must not be able to pretend it verifies anything.
 */
final class TrustAnchorsTest extends TestCase
{
    public function testProductionRingIsNotEmptyAndIsStructurallyValid(): void
    {
        $anchors = new TrustAnchors();

        $anchors->assertUsable();

        self::assertNotSame([], $anchors->keyIds());
        self::assertContains('vtone-2026a', $anchors->keyIds());
    }

    public function testProductionKeyIsExactlyThePublishedOne(): void
    {
        $anchors = new TrustAnchors();
        $key = $anchors->key('vtone-2026a', 'ed25519', TrustAnchors::PURPOSE_ENVELOPE);

        self::assertSame(32, \strlen($key), 'An Ed25519 public key is 32 raw bytes.');
        self::assertTrue(
            $anchors->activeKeyMatchesFingerprint(),
            'The reassembled key must still match the published fingerprint.',
        );
    }

    public function testEmptyRingFailsClosed(): void
    {
        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('signing_key_store_empty');

        (new TrustAnchors([]))->assertUsable();
    }

    public function testPlaceholderKeyIsRejected(): void
    {
        $ring = [[
            'id' => 'placeholder',
            'algorithm' => 'ed25519',
            'key' => str_repeat("\0", 32),
            'purposes' => [TrustAnchors::PURPOSE_RECORD],
            'from' => 0,
            'until' => null,
        ]];

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('signing_key_store_empty');

        (new TrustAnchors($ring))->assertUsable();
    }

    public function testTruncatedKeyIsRejected(): void
    {
        $ring = [[
            'id' => 'short',
            'algorithm' => 'ed25519',
            'key' => 'too-short',
            'purposes' => [TrustAnchors::PURPOSE_RECORD],
            'from' => 0,
            'until' => null,
        ]];

        $this->expectException(PackageRejected::class);

        (new TrustAnchors($ring))->assertUsable();
    }

    public function testUnknownKeyIdIsRejected(): void
    {
        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('unknown_signing_key');

        (new TrustAnchors())->key('vtone-1999x', 'ed25519', TrustAnchors::PURPOSE_ENVELOPE);
    }

    public function testUnsupportedAlgorithmIsRejected(): void
    {
        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('unsupported_signature_algorithm');

        (new TrustAnchors())->key('vtone-2026a', 'rsa-pss', TrustAnchors::PURPOSE_ENVELOPE);
    }

    public function testRetiredKeyIsNotUsable(): void
    {
        $ring = [[
            'id' => 'retired',
            'algorithm' => 'ed25519',
            'key' => random_bytes(32),
            'purposes' => [TrustAnchors::PURPOSE_ENVELOPE],
            'from' => 0,
            'until' => time() - 10,
        ]];

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('unknown_signing_key');

        (new TrustAnchors($ring))->key('retired', 'ed25519', TrustAnchors::PURPOSE_ENVELOPE);
    }

    public function testKeyIsNotUsableForAnotherPurpose(): void
    {
        $ring = [[
            'id' => 'envelope-only',
            'algorithm' => 'ed25519',
            'key' => random_bytes(32),
            'purposes' => [TrustAnchors::PURPOSE_ENVELOPE],
            'from' => 0,
            'until' => null,
        ]];

        $this->expectException(PackageRejected::class);

        (new TrustAnchors($ring))->key('envelope-only', 'ed25519', TrustAnchors::PURPOSE_REQUEST);
    }
}
