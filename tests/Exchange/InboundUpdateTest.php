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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\Controller\ServiceCallbackController;
use VTInnovations\AccessPlus\Exchange\InboundUpdate;
use VTInnovations\AccessPlus\Exchange\RequestJournal;
use VTInnovations\AccessPlus\Exchange\ServiceEndpoints;
use VTInnovations\AccessPlus\Security\CallbackAuthenticator;
use VTInnovations\AccessPlus\Security\CanonicalForm;
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\State\DomainInventory;
use VTInnovations\AccessPlus\State\RegistrationStore;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteStatusProvider;
use VTInnovations\AccessPlus\Tests\Support\CapturingLogger;
use VTInnovations\AccessPlus\Tests\Support\TestPackageFactory;

/**
 * The server-initiated update endpoint. Trust comes from the signature and
 * nothing else, and every documented rejection case is asserted.
 */
final class InboundUpdateTest extends TestCase
{
    private TestPackageFactory $factory;

    private CapturingLogger $logger;

    private string $projectDir;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('libsodium is not available in this runtime.');
        }

        $this->factory = new TestPackageFactory();
        $this->logger = new CapturingLogger();
        $this->projectDir = sys_get_temp_dir() . '/accessplus-callback-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    public function testValidSignedUpdateIsApplied(): void
    {
        $request = $this->signedRequest($this->body());
        $result = $this->handler()->handle($request);

        self::assertSame(200, $result['status']);
        self::assertSame('updated', $result['body']['status']);
        self::assertSame(9, $result['body']['license_version']);
        self::assertNotNull((new RegistrationStore($this->projectDir))->read(5));
    }

    public function testExactRetryIsIdempotent(): void
    {
        $body = $this->body();
        $handler = $this->handler();

        $handler->handle($this->signedRequest($body));
        $again = $handler->handle($this->signedRequest($body));

        self::assertSame(200, $again['status']);
        self::assertSame('already_processed', $again['body']['status']);
        self::assertSame(9, $again['body']['license_version']);
    }

    public function testSameRequestIdWithDifferentContentIsRejected(): void
    {
        $handler = $this->handler();
        $handler->handle($this->signedRequest($this->body()));

        $conflicting = $this->body(['license_version' => 10], 'req-fixed');
        $result = $handler->handle($this->signedRequest($conflicting));

        self::assertSame(403, $result['status']);
        self::assertSame('rejected', $result['body']['status']);
    }

    public function testReplayedNonceIsRejected(): void
    {
        $handler = $this->handler();
        $handler->handle($this->signedRequest($this->body()));

        // New request id, deliberately reusing the nonce of the first call.
        $replay = $this->body(['license_version' => 10], 'req-2', null, 'example.com', 'server-nonce-req-fixed');
        $result = $handler->handle($this->signedRequest($replay));

        self::assertSame(403, $result['status']);
    }

    public function testUnsignedRequestIsRejected(): void
    {
        $body = json_encode($this->body());
        $request = Request::create(ServiceEndpoints::CALLBACK_PATH, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $result = $this->handler()->handle($request);

        self::assertSame(401, $result['status']);
        self::assertSame(['status' => 'rejected'], $result['body']);
    }

    public function testTamperedBodyBreaksTheSignature(): void
    {
        $request = $this->signedRequest($this->body(), static fn (string $raw): string => str_replace('example.com', 'evil.test', $raw));

        self::assertSame(401, $this->handler()->handle($request)['status']);
    }

    public function testStaleTimestampIsRejected(): void
    {
        $request = $this->signedRequest($this->body(), null, time() - 3600);

        self::assertSame(401, $this->handler()->handle($request)['status']);
    }

    public function testHeaderAndBodyMetadataMustAgree(): void
    {
        $body = $this->body();
        $request = $this->signedRequest($body);
        $request->headers->set('X-VT-Nonce', 'another-nonce-value');

        // Changing the header invalidates the signature first — still 401, and
        // never an accepted update.
        self::assertNotSame(200, $this->handler()->handle($request)['status']);
    }

    public function testWrongMediaTypeAndOversizedBodyAreRejected(): void
    {
        $body = json_encode($this->body());
        $request = Request::create(ServiceEndpoints::CALLBACK_PATH, 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

        self::assertSame(415, $this->handler()->handle($request)['status']);

        $huge = $this->signedRequest($this->body(), static fn (string $raw): string => $raw . str_repeat(' ', 300000));
        self::assertSame(413, $this->handler()->handle($huge)['status']);
    }

    public function testUnknownDomainHasNoScopeHere(): void
    {
        $document = $this->factory->document([
            'license_version' => 9,
            'license_domain' => 'not-configured.example.net',
            'license_domains' => ['not-configured.example.net'],
        ]);

        $result = $this->handler()->handle($this->signedRequest($this->body([], 'req-x', $document, 'not-configured.example.net')));

        self::assertSame(403, $result['status']);
    }

    public function testOlderVersionCannotRollTheStateBack(): void
    {
        $handler = $this->handler();
        $handler->handle($this->signedRequest($this->body()));

        $older = $this->body(['license_version' => 8], 'req-older');
        $result = $handler->handle($this->signedRequest($older));

        self::assertSame(409, $result['status']);

        $stored = (new RegistrationStore($this->projectDir))->read(5);
        self::assertSame(9, $stored['envelope']['license_version']);
    }

    public function testSignedRevocationIsAppliedAsAnAuthenticUpdate(): void
    {
        $document = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 9,
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);

        $result = $this->handler()->handle($this->signedRequest($this->body([], 'req-rev', $document)));

        self::assertSame(200, $result['status']);
        self::assertSame('updated', $result['body']['status']);
        self::assertSame(9, $result['body']['license_version']);

        $store = new RegistrationStore($this->projectDir);
        self::assertNotNull($store->readTombstone(5), 'A durable tombstone must be written.');
        self::assertFalse($this->provider($store)->forRoot(5)->isActive());
    }

    public function testRevocationTargetingAHostRemovedFromTheAuthorisedSetIsAccepted(): void
    {
        // license_domain is intentionally absent from the new license_domains.
        $document = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 9,
            'license_domain' => 'example.com',
            'license_domains' => ['successor.example.org'],
        ]);

        $result = $this->handler()->handle($this->signedRequest($this->body([], 'req-xfer', $document)));

        self::assertSame(200, $result['status']);
        self::assertSame('updated', $result['body']['status']);
    }

    public function testExactReplayOfARevocationIsIdempotent(): void
    {
        $document = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 9,
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);
        $body = $this->body([], 'req-rev2', $document);
        $handler = $this->handler();

        $handler->handle($this->signedRequest($body));
        $again = $handler->handle($this->signedRequest($body));

        self::assertSame('already_processed', $again['body']['status']);
        self::assertSame(9, $again['body']['license_version']);
    }

    public function testRevocationCannotBeUndoneByRestoringAnOlderValidFile(): void
    {
        $handler = $this->handler();
        $revoked = $this->factory->document([
            'validation_status' => 'revoked',
            'license_version' => 30,
            'license_domain' => 'example.com',
            'license_domains' => [],
        ]);
        $handler->handle($this->signedRequest($this->body([], 'req-rev3', $revoked)));

        // Filesystem restore of a historically valid state file at v12.
        $valid = $this->factory->document(['license_version' => 12]);
        $vb = $this->factory->bytes($valid);
        file_put_contents(
            $this->projectDir . '/var/accessplus/roots/5/state.json',
            (string) json_encode(['payload_b64' => base64_encode($vb), 'integrity' => $this->factory->envelope($vb, 12), 'stored_at' => time()]),
        );

        self::assertFalse($this->provider(new RegistrationStore($this->projectDir))->forRoot(5)->isActive());
    }

    public function testGetIsAnsweredWithMethodNotAllowed(): void
    {
        $controller = new ServiceCallbackController($this->handler());
        $response = $controller(Request::create(ServiceEndpoints::CALLBACK_PATH, 'GET'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->headers->get('Allow'));
    }

    public function testCallbackLogsCarryNoPacketContent(): void
    {
        $this->handler()->handle($this->signedRequest($this->body()));

        $dump = $this->logger->dump();

        self::assertStringNotContainsString('AP-TEST-0000-1111', $dump);
        self::assertStringNotContainsString('license_payload_b64', $dump);
        self::assertStringNotContainsString('nonce', $dump);
        self::assertStringNotContainsString('signature', $dump);
    }

    /**
     * @param array<string, mixed>      $overrides
     * @param array<string, mixed>|null $document
     *
     * @return array<string, mixed>
     */
    private function body(array $overrides = [], string $requestId = 'req-fixed', ?array $document = null, string $domain = 'example.com', ?string $nonce = null): array
    {
        $document ??= $this->factory->document(['license_version' => 9]);

        if (isset($overrides['license_version'])) {
            $document['license_version'] = $overrides['license_version'];
        }

        $bytes = $this->factory->bytes($document);

        return [
            'action' => 'license_update',
            'project' => ServiceEndpoints::PROJECT,
            'project_slug' => ServiceEndpoints::PROJECT_SLUG,
            'product_id' => ServiceEndpoints::PRODUCT_ID,
            'domain' => $domain,
            'request_id' => $requestId,
            'timestamp' => time(),
            'nonce' => $nonce ?? ('server-nonce-' . $requestId),
            'license_payload_b64' => base64_encode($bytes),
            'integrity' => $this->factory->envelope($bytes, (int) $document['license_version']),
        ];
    }

    /**
     * @param array<string, mixed>            $body
     * @param (\Closure(string): string)|null $mangle applied AFTER signing
     */
    private function signedRequest(array $body, ?\Closure $mangle = null, ?int $timestamp = null): Request
    {
        $raw = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp ??= (int) $body['timestamp'];

        $message = CanonicalForm::request(
            'POST',
            ServiceEndpoints::CALLBACK_PATH,
            (string) $body['request_id'],
            $timestamp,
            (string) $body['nonce'],
            CanonicalForm::bodyHash($raw),
        );

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_VT_REQUEST_ID' => (string) $body['request_id'],
            'HTTP_X_VT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_VT_NONCE' => (string) $body['nonce'],
            'HTTP_X_VT_KEY_ID' => 'test-key',
            'HTTP_X_VT_SIGNATURE' => $this->factory->sign($message),
        ];

        if ($mangle !== null) {
            $raw = $mangle($raw);
        }

        return Request::create(ServiceEndpoints::CALLBACK_PATH, 'POST', [], [], [], $headers, $raw);
    }

    /**
     * A fresh SiteStatusProvider over the same on-disk state, as a later request
     * would see it.
     */
    private function provider(RegistrationStore $store): SiteStatusProvider
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
        ]);

        $scope = new RootScope($connection);
        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));
        $reader = new SealedPackageReader($this->factory->anchors());

        return new SiteStatusProvider($store, $reader, new DomainInventory($scope, $stack), $scope);
    }

    private function handler(): InboundUpdate
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
        ]);

        $scope = new RootScope($connection);
        $stack = new RequestStack();
        $inventory = new DomainInventory($scope, $stack);
        $reader = new SealedPackageReader($this->factory->anchors());
        $store = new RegistrationStore($this->projectDir);

        return new InboundUpdate(
            new CallbackAuthenticator($this->factory->anchors()),
            $reader,
            $store,
            new SiteStatusProvider($store, $reader, $inventory, $scope),
            $inventory,
            new RequestJournal($this->projectDir),
            $this->logger,
        );
    }
}
