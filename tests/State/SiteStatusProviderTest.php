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

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\State\DomainInventory;
use VTInnovations\AccessPlus\State\RegistrationStore;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteState;
use VTInnovations\AccessPlus\State\SiteStatusProvider;
use VTInnovations\AccessPlus\Tests\Support\TestPackageFactory;

/**
 * The product policy: Pro only, exact host binding per site root, server-side
 * dates, and no fallback of any kind.
 */
final class SiteStatusProviderTest extends TestCase
{
    private TestPackageFactory $factory;

    private string $projectDir;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('libsodium is not available in this runtime.');
        }

        $this->factory = new TestPackageFactory();
        $this->projectDir = sys_get_temp_dir() . '/accessplus-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    public function testValidProPackageActivatesTheRoot(): void
    {
        $status = $this->provider()->evaluate($this->factory->document(), 5);

        self::assertSame(SiteState::Active, $status->state);
        self::assertTrue($status->isActive());
        self::assertSame('example.com', $status->matchedDomain);
        self::assertSame('pro', $status->package);
        self::assertSame(7, $status->version);
        self::assertTrue($status->hasKey());
    }

    /**
     * @dataProvider rejectedPackages
     */
    public function testOnlyProIsAccepted(string $package): void
    {
        $status = $this->provider()->evaluate($this->factory->document(['license_package' => $package]), 5);

        self::assertSame(SiteState::Invalid, $status->state);
        self::assertSame('package_not_permitted', $status->reason);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function rejectedPackages(): iterable
    {
        yield ['free'];
        yield ['trial'];
        yield ['starter'];
        yield [''];
    }

    public function testFreeAvailableDoesNotCreateAccessInThisModel(): void
    {
        $document = $this->factory->document([
            'license_package' => 'free',
            'free_available' => true,
            'license_lifetime' => true,
            'license_expires_at' => null,
        ]);

        self::assertSame(SiteState::Invalid, $this->provider()->evaluate($document, 5)->state);
    }

    public function testExpiredProHasNoFallback(): void
    {
        $document = $this->factory->document([
            'license_starts_at' => time() - 200,
            'license_expires_at' => time() - 100,
            'free_available' => true,
        ]);

        $status = $this->provider()->evaluate($document, 5);

        self::assertSame(SiteState::Expired, $status->state);
        self::assertFalse($status->isActive());
    }

    public function testNotYetValidIsNotActive(): void
    {
        $document = $this->factory->document(['license_starts_at' => time() + 3600]);

        self::assertSame('not_yet_valid', $this->provider()->evaluate($document, 5)->reason);
    }

    public function testNonLifetimeWithoutExpiryIsRejected(): void
    {
        $document = $this->factory->document(['license_expires_at' => null]);

        self::assertSame('expiry_invalid', $this->provider()->evaluate($document, 5)->reason);
    }

    public function testLifetimeWithExpiryIsRejected(): void
    {
        $document = $this->factory->document(['license_lifetime' => true]);

        self::assertSame('lifetime_expiry_conflict', $this->provider()->evaluate($document, 5)->reason);
    }

    public function testHostMustIntersectTheConfiguredDomainExactly(): void
    {
        $provider = $this->provider('example.com');

        $wrongApex = $this->factory->document([
            'license_domain' => 'www.example.com',
            'license_domains' => ['www.example.com'],
        ]);
        self::assertSame('domain_not_configured', $provider->evaluate($wrongApex, 5)->reason);

        $subdomain = $this->factory->document([
            'license_domain' => 'shop.example.com',
            'license_domains' => ['shop.example.com'],
        ]);
        self::assertSame('domain_not_configured', $provider->evaluate($subdomain, 5)->reason);
    }

    public function testOperationDomainMustBeAMemberOfTheSignedSet(): void
    {
        $document = $this->factory->document([
            'license_domain' => 'example.com',
            'license_domains' => ['other.example.com', 'staging.example.com'],
        ]);

        self::assertSame('domain_binding_invalid', $this->provider()->evaluate($document, 5)->reason);
    }

    public function testRequestDomainMustEqualTheSignedOperationDomain(): void
    {
        $status = $this->provider()->evaluate($this->factory->document(), 5, 'staging.example.com');

        self::assertSame('domain_mismatch', $status->reason);
    }

    public function testUnsortedOrWildcardDomainSetIsRejected(): void
    {
        $unsorted = $this->factory->document(['license_domains' => ['staging.example.com', 'example.com']]);
        self::assertSame('domain_set_invalid', $this->provider()->evaluate($unsorted, 5)->reason);

        $wildcard = $this->factory->document([
            'license_domain' => 'example.com',
            'license_domains' => ['*.example.com', 'example.com'],
        ]);
        self::assertSame('domain_set_invalid', $this->provider()->evaluate($wildcard, 5)->reason);
    }

    public function testInstanceBoundAllowanceIsNotAWildcard(): void
    {
        $document = $this->factory->document([
            'license_max_domains' => 9999,
            'license_domain' => 'other.example.com',
            'license_domains' => ['other.example.com'],
        ]);

        self::assertSame('domain_not_configured', $this->provider()->evaluate($document, 5)->reason);
    }

    public function testBoundCountAboveTheAllowanceStaysValid(): void
    {
        $document = $this->factory->document([
            'license_max_domains' => 1,
            'license_domains' => ['example.com', 'staging.example.com'],
        ]);

        self::assertSame(SiteState::Active, $this->provider()->evaluate($document, 5)->state);
    }

    public function testAllowanceMustBeAPositiveInteger(): void
    {
        self::assertSame('allowance_invalid', $this->provider()->evaluate($this->factory->document(['license_max_domains' => 0]), 5)->reason);
        self::assertSame('allowance_invalid', $this->provider()->evaluate($this->factory->document(['license_max_domains' => '3']), 5)->reason);
    }

    public function testLegacyRecordWithoutTheSignedHostSetRequiresARefresh(): void
    {
        $document = $this->factory->document();
        unset($document['license_domains'], $document['license_max_domains']);

        $status = $this->provider()->evaluate($document, 5);

        self::assertSame('refresh_required', $status->reason);
        self::assertTrue($status->hasKey(), 'The key is kept so a refresh can be attempted.');
    }

    public function testSignedRevocationIsAnAuthenticNegativeState(): void
    {
        $document = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 12,
            'license_domain' => 'example.com',
            'license_domains' => ['successor.example.org'],
        ]);

        $status = $this->provider()->evaluate($document, 5);

        self::assertSame(SiteState::Revoked, $status->state);
        self::assertFalse($status->isActive());
        self::assertSame(12, $status->version);
        self::assertTrue($status->hasKey());
    }

    public function testRevokedStateMayTargetAHostAbsentFromTheAuthorisedSet(): void
    {
        // Domain transfer / full withdrawal: this host is deliberately not in
        // the new authorised set. That must NOT be a rejection.
        $document = $this->factory->document([
            'validation_status' => 'revoked',
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);

        self::assertSame(SiteState::Revoked, $this->provider()->evaluate($document, 5, 'example.com')->state);
    }

    public function testRevokedStateStillMustBeAddressedToThisInstallation(): void
    {
        $document = $this->factory->document([
            'validation_status' => 'revoked',
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);

        self::assertSame('domain_mismatch', $this->provider()->evaluate($document, 5, 'staging.example.com')->reason);
    }

    public function testAnUnknownValidationStatusIsRejected(): void
    {
        self::assertSame('status_not_valid', $this->provider()->evaluate($this->factory->document(['validation_status' => 'suspended']), 5)->reason);
    }

    public function testLeaseGraceCutoffFailsClosed(): void
    {
        $document = $this->factory->document([
            'license_refresh_required_at' => time() - 400,
            'license_grace_until' => time() - 100,
        ]);

        $status = $this->provider()->evaluate($document, 5);

        self::assertFalse($status->isActive());
        self::assertSame(SiteState::Expired, $status->state);
        self::assertSame('lease_expired', $status->reason);
    }

    public function testInsideTheGraceWindowTheStateStaysActiveButRefreshIsDue(): void
    {
        $document = $this->factory->document([
            'license_refresh_required_at' => time() - 100,
            'license_grace_until' => time() + 3600,
        ]);

        $status = $this->provider()->evaluate($document, 5);

        self::assertTrue($status->isActive());
        self::assertTrue($status->isRefreshDue());
    }

    public function testAMalformedLeasePolicyIsRejected(): void
    {
        self::assertSame('lease_policy_invalid', $this->provider()->evaluate($this->factory->document([
            'license_refresh_required_at' => time() + 1000,
            'license_grace_until' => time() + 100,
        ]), 5)->reason);
    }

    public function testTombstoneOutranksARestoredOlderValidStateFile(): void
    {
        $provider = $this->provider();

        // A durable tombstone at v20.
        $revoked = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 20,
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);
        $bytes = $this->factory->bytes($revoked);
        (new RegistrationStore($this->projectDir))->commit(
            5,
            $bytes,
            $this->factory->envelope($bytes, 20),
            static fn () => null,
            true,
        );

        // An administrator restores a historically valid state file at v7
        // straight onto disk (bypassing commit, as a backup restore would).
        $valid = $this->factory->document(['license_version' => 7]);
        $vb = $this->factory->bytes($valid);
        file_put_contents(
            $this->projectDir . '/var/accessplus/roots/5/state.json',
            (string) json_encode(['payload_b64' => base64_encode($vb), 'integrity' => $this->factory->envelope($vb, 7), 'stored_at' => time()]),
        );

        $provider->forget(5);

        self::assertSame(SiteState::Revoked, $provider->forRoot(5)->state);
        self::assertFalse($provider->forRoot(5)->isActive());
    }

    public function testTombstoneSurvivesRemovalOfTheLiveState(): void
    {
        $provider = $this->provider();
        $store = new RegistrationStore($this->projectDir);

        $revoked = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 20,
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);
        $bytes = $this->factory->bytes($revoked);
        $store->commit(5, $bytes, $this->factory->envelope($bytes, 20), static fn () => null, true);
        $store->remove(5);
        $provider->forget(5);

        self::assertSame(SiteState::Revoked, $provider->forRoot(5)->state);
    }

    public function testForeignProductIsRejected(): void
    {
        self::assertSame('foreign_product', $this->provider()->evaluate($this->factory->document(['project_slug' => 'guardian']), 5)->reason);
        self::assertSame('foreign_product', $this->provider()->evaluate($this->factory->document(['project' => 'Guardian']), 5)->reason);
    }

    public function testStoredStateIsScopedToItsOwnRoot(): void
    {
        $provider = $this->provider('example.com', [
            ['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
            ['id' => 6, 'dns' => 'second.example.org', 'language' => 'de', 'title' => 'B', 'useSSL' => 1],
        ]);

        $this->store(5, $this->factory->document());

        self::assertTrue($provider->forRoot(5)->isActive());
        self::assertSame(SiteState::Unlicensed, $provider->forRoot(6)->state, 'One root never licenses another.');
        self::assertSame([5], $provider->activeRootIds());
    }

    public function testCopyingTheStateToAnotherRootDoesNotWork(): void
    {
        $provider = $this->provider('example.com', [
            ['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
            ['id' => 6, 'dns' => 'second.example.org', 'language' => 'de', 'title' => 'B', 'useSSL' => 1],
        ]);

        // Byte-identical state, put into another scope's directory.
        $this->store(6, $this->factory->document());

        self::assertFalse($provider->forRoot(6)->isActive());
        self::assertSame('domain_not_configured', $provider->forRoot(6)->reason);
    }

    public function testHandEditedStateIsRejected(): void
    {
        $provider = $this->provider();
        $document = $this->factory->document(['license_expires_at' => time() - 10]);
        $this->store(5, $document);

        self::assertSame(SiteState::Expired, $provider->forRoot(5)->state);

        // Push the expiry into the future by editing the stored file directly.
        $path = $this->projectDir . '/var/accessplus/roots/5/state.json';
        $raw = json_decode((string) file_get_contents($path), true);
        $bytes = base64_decode($raw['payload_b64'], true);
        $patched = str_replace(
            '"license_expires_at":' . $document['license_expires_at'],
            '"license_expires_at":' . (time() + 99999),
            $bytes,
        );
        self::assertNotSame($bytes, $patched);
        $raw['payload_b64'] = base64_encode($patched);
        file_put_contents($path, json_encode($raw));

        $provider->forget(5);

        self::assertSame(SiteState::Invalid, $provider->forRoot(5)->state);
        self::assertSame('payload_digest_mismatch', $provider->forRoot(5)->reason);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function store(int $rootId, array $document): void
    {
        $bytes = $this->factory->bytes($document);
        $envelope = $this->factory->envelope($bytes, (int) $document['license_version']);

        (new RegistrationStore($this->projectDir))->commit($rootId, $bytes, $envelope, static fn () => null);
    }

    /**
     * @param list<array<string, mixed>> $roots
     */
    private function provider(string $currentHost = 'example.com', ?array $roots = null): SiteStatusProvider
    {
        $roots ??= [['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1]];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($roots);

        $scope = new RootScope($connection);
        $stack = new RequestStack();
        $stack->push(\Symfony\Component\HttpFoundation\Request::create('https://' . $currentHost . '/'));

        return new SiteStatusProvider(
            new RegistrationStore($this->projectDir),
            new SealedPackageReader($this->factory->anchors()),
            new DomainInventory($scope, $stack),
            $scope,
        );
    }
}
