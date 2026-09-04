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

namespace VTInnovations\AccessPlus\Tests\Exchange;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\Cron\LicenseRefreshCron;
use VTInnovations\AccessPlus\Exchange\ExchangeClient;
use VTInnovations\AccessPlus\Exchange\ServiceEndpoints;
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\State\BackendSessionClaim;
use VTInnovations\AccessPlus\State\DomainInventory;
use VTInnovations\AccessPlus\State\RegistrationStore;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteRegistrar;
use VTInnovations\AccessPlus\State\SiteState;
use VTInnovations\AccessPlus\State\SiteStatusProvider;
use VTInnovations\AccessPlus\Tests\Support\CapturingLogger;
use VTInnovations\AccessPlus\Tests\Support\TestPackageFactory;

/**
 * The pull half of revocation: when a server push never arrives, the signed
 * refresh lease must still take the installation offline.
 *
 * No live request leaves the test: the vendor endpoint is a {@see MockHttpClient}
 * that echoes back a signed `revoked` state, exactly as the real server would
 * once the domain set changed.
 */
final class LeaseRefreshTest extends TestCase
{
    private TestPackageFactory $factory;

    private string $projectDir;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('libsodium is not available in this runtime.');
        }

        $this->factory = new TestPackageFactory();
        $this->projectDir = sys_get_temp_dir() . '/accessplus-lease-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    public function testLeaseRefreshEventuallyDisablesStaleEntitlementWhenPushNeverSucceeds(): void
    {
        $store = new RegistrationStore($this->projectDir);

        // A currently active state whose refresh deadline has already passed but
        // whose grace window is still open — the exact moment the job must act.
        $valid = $this->factory->document([
            'license_version' => 10,
            'license_refresh_required_at' => time() - 100,
            'license_grace_until' => time() + 86400,
        ]);
        $vb = $this->factory->bytes($valid);
        $store->commit(5, $vb, $this->factory->envelope($vb, 10), static fn () => null);

        $provider = $this->provider($store);
        self::assertTrue($provider->forRoot(5)->isActive());
        self::assertTrue($provider->forRoot(5)->isRefreshDue());

        // The vendor now answers refresh with a signed revocation at v15.
        $revoked = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 15,
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);
        $revokedBytes = $this->factory->bytes($revoked);
        $requests = 0;

        $cron = $this->cron($store, $provider, $this->httpClient($revokedBytes, 15, $requests));
        $cron('cli');

        self::assertSame(1, $requests, 'The job must call the vendor exactly once.');

        $provider->forget(5);
        self::assertSame(SiteState::Revoked, $provider->forRoot(5)->state);
        self::assertFalse($provider->forRoot(5)->isActive());
        self::assertNotNull($store->readTombstone(5), 'The pulled revocation is persisted as a durable tombstone.');
        self::assertSame(15, $store->readTombstone(5)['version']);
    }

    public function testATransportFailureLeavesThePreviousStateUntouched(): void
    {
        $store = new RegistrationStore($this->projectDir);
        $valid = $this->factory->document([
            'license_version' => 10,
            'license_refresh_required_at' => time() - 100,
            'license_grace_until' => time() + 86400,
        ]);
        $vb = $this->factory->bytes($valid);
        $store->commit(5, $vb, $this->factory->envelope($vb, 10), static fn () => null);

        $provider = $this->provider($store);
        $failing = new MockHttpClient(static function (): MockResponse {
            return new MockResponse('', ['error' => 'connection refused']);
        });

        $this->cron($store, $provider, $failing)('cli');

        $provider->forget(5);
        self::assertTrue($provider->forRoot(5)->isActive(), 'A transient failure must not disturb the current state.');
    }

    private function httpClient(string $revokedBytes, int $version, int &$requests): MockHttpClient
    {
        $factory = $this->factory;

        return new MockHttpClient(static function (string $method, string $url, array $options) use ($revokedBytes, $version, &$requests, $factory): MockResponse {
            ++$requests;
            $body = json_decode((string) $options['body'], true);

            $payload = [
                'status' => 'revoked',
                'request_id' => $body['request_id'],
                'server_time' => time(),
                'license_payload_b64' => base64_encode($revokedBytes),
                'integrity' => $factory->envelope($revokedBytes, $version),
            ];

            return new MockResponse(
                (string) json_encode($payload),
                ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
            );
        });
    }

    private function cron(RegistrationStore $store, SiteStatusProvider $provider, MockHttpClient $http): LicenseRefreshCron
    {
        $scope = $this->rootScope();
        $stack = new RequestStack();
        $inventory = new DomainInventory($scope, $stack);
        $reader = new SealedPackageReader($this->factory->anchors());

        $client = new ExchangeClient($http, new ServiceEndpoints(), $reader, new CapturingLogger());

        $registrar = new SiteRegistrar(
            $client,
            $store,
            $reader,
            $provider,
            $inventory,
            $scope,
            new BackendSessionClaim($stack),
        );

        return new LicenseRefreshCron($scope, $provider, $registrar);
    }

    private function provider(RegistrationStore $store): SiteStatusProvider
    {
        $scope = $this->rootScope();
        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));

        return new SiteStatusProvider($store, new SealedPackageReader($this->factory->anchors()), new DomainInventory($scope, $stack), $scope);
    }

    private function rootScope(): RootScope
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
        ]);

        return new RootScope($connection);
    }
}
