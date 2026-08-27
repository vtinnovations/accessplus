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
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\Security\TrustAnchors;

/**
 * Cross-system vector against the PINNED PRODUCTION KEY.
 *
 * The other tests prove the verification logic with a throw-away key pair. Only
 * this one proves that our canonical byte forms match the vendor's, because only
 * the vendor can produce a signature for the pinned key.
 *
 * How to enable it: save one real successful `activate` response body (the whole
 * JSON object with `license_payload_b64` and `integrity`) as
 * tests/Fixtures/vendor-response.json. The file is a verification sample, not a
 * secret — but it does contain a licence key, so keep it out of public
 * repositories.
 *
 * Until that file exists the test is SKIPPED, and cross-system compatibility
 * counts as unverified.
 */
final class VendorVectorTest extends TestCase
{
    public function testPinnedProductionKeyVerifiesARealVendorResponse(): void
    {
        $path = \dirname(__DIR__) . '/Fixtures/vendor-response.json';

        if (!is_file($path)) {
            self::markTestSkipped('No vendor response fixture present — cross-system vector not verified.');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($payload, 'The fixture must be the raw response body.');

        $package = (new SealedPackageReader(new TrustAnchors()))->open($payload);

        self::assertSame('AccessPlus', $package->document['project'] ?? null);
        self::assertSame('accessplus', $package->document['project_slug'] ?? null);
        self::assertSame(2, $package->document['schema_version'] ?? null);
        self::assertGreaterThan(0, $package->version());
    }
}
