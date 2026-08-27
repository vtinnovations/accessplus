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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VTInnovations\AccessPlus\Exchange\ExchangeClient;
use VTInnovations\AccessPlus\Exchange\ServiceEndpoints;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\Tests\Support\CapturingLogger;
use VTInnovations\AccessPlus\Tests\Support\TestPackageFactory;

/**
 * Outbound transport: fixed destination, exact packet shape, response
 * validation, and the log-secrecy rules.
 *
 * No test in this file contacts a real host — every request is answered by a
 * mock client.
 */
final class ExchangeClientTest extends TestCase
{
    private TestPackageFactory $factory;

    private CapturingLogger $logger;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('libsodium is not available in this runtime.');
        }

        $this->factory = new TestPackageFactory();
        $this->logger = new CapturingLogger();
    }

    public function testEndpointsAreTheFixedVendorOnes(): void
    {
        $endpoints = new ServiceEndpoints();

        self::assertSame('https://www.v-t.one/api/v1/verify', $endpoints->verify());
        self::assertSame('https://www.v-t.one/rest/api/v1/log-envoke', $endpoints->signal());
        self::assertSame('www.v-t.one', $endpoints->host());
        self::assertSame('/rest/api/v1/accessplus-license-updater', ServiceEndpoints::CALLBACK_PATH);

        self::assertFalse($endpoints->isOwnDestination('http://www.v-t.one/api/v1/verify'), 'no plain http');
        self::assertFalse($endpoints->isOwnDestination('https://www.v-t.one.evil.test/api/v1/verify'));
        self::assertFalse($endpoints->isOwnDestination('https://user:pw@www.v-t.one/api/v1/verify'));
        self::assertFalse($endpoints->isOwnDestination('https://www.v-t.one:8443/api/v1/verify'));
    }

    public function testActivationSendsTheDocumentedPacketAndOpensTheResponse(): void
    {
        $captured = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];
            $body = json_decode((string) $options['body'], true);

            return new MockResponse(
                json_encode($this->factory->response($this->factory->document(), (string) $body['request_id'])),
                ['response_headers' => ['content-type' => 'application/json']],
            );
        });

        $package = $this->client($client)->activate('AP-TEST-0000-1111', 'example.com');

        self::assertSame(7, $package->version());
        self::assertSame('POST', $captured['method']);
        self::assertSame('https://www.v-t.one/api/v1/verify', $captured['url']);
        self::assertSame(0, $captured['options']['max_redirects'], 'redirects must never be followed');
        self::assertTrue($captured['options']['verify_peer']);
        self::assertTrue($captured['options']['verify_host']);

        $sent = json_decode((string) $captured['options']['body'], true);
        self::assertSame('activate', $sent['action']);
        self::assertSame('AccessPlus', $sent['project']);
        self::assertSame('accessplus', $sent['project_slug']);
        self::assertSame('vt-accessplus', $sent['product_id']);
        self::assertSame('example.com', $sent['domain']);
        self::assertNotSame('', $sent['nonce']);
        self::assertArrayNotHasKey('current_license_version', $sent);
    }

    public function testRefreshSendsTheCurrentVersion(): void
    {
        $sent = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = json_decode((string) $options['body'], true);

            return new MockResponse(
                json_encode($this->factory->response($this->factory->document(['license_version' => 8]), (string) $sent['request_id'])),
                ['response_headers' => ['content-type' => 'application/json']],
            );
        });

        $package = $this->client($client)->refresh('AP-TEST-0000-1111', 'example.com', 7);

        self::assertSame('refresh', $sent['action']);
        self::assertSame(7, $sent['current_license_version']);
        self::assertSame(8, $package->version());
    }

    public function testAResponseForAnotherRequestIdIsRejected(): void
    {
        $client = new MockHttpClient(fn (): MockResponse => new MockResponse(
            json_encode($this->factory->response($this->factory->document(), 'someone-elses-request')),
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('correlation_failed');

        $this->client($client)->activate('AP-TEST-0000-1111', 'example.com');
    }

    public function testNonJsonResponseIsRejectedBeforeParsing(): void
    {
        $html = new MockHttpClient(fn (): MockResponse => new MockResponse('<html>error</html>', ['response_headers' => ['content-type' => 'text/html']]));

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('media_type_rejected');

        $this->client($html)->activate('AP-TEST-0000-1111', 'example.com');
    }

    public function testServerErrorPreservesNothingAndReportsTransportFailure(): void
    {
        $client = new MockHttpClient(fn (): MockResponse => new MockResponse('{}', ['http_code' => 503, 'response_headers' => ['content-type' => 'application/json']]));

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('service_unavailable');

        $this->client($client)->activate('AP-TEST-0000-1111', 'example.com');
    }

    public function testDenialIsNotTreatedAsSuccess(): void
    {
        $client = new MockHttpClient(fn (string $m, string $u, array $o): MockResponse => new MockResponse(
            json_encode(['status' => 'invalid', 'request_id' => json_decode((string) $o['body'], true)['request_id']]),
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('service_denied');

        $this->client($client)->activate('AP-TEST-0000-1111', 'example.com');
    }

    /**
     * Packet-log secrecy: neither a success nor a failure may put packet content
     * into ordinary logs.
     */
    public function testLogsContainNoPacketContent(): void
    {
        $key = 'AP-SECRET-KEY-9999';
        $document = $this->factory->document(['license_key' => $key]);

        $ok = new MockHttpClient(fn (string $m, string $u, array $o): MockResponse => new MockResponse(
            json_encode($this->factory->response($document, json_decode((string) $o['body'], true)['request_id'])),
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $this->client($ok)->activate($key, 'example.com');

        $bad = new MockHttpClient(fn (): MockResponse => new MockResponse('{"status":"nope"}', ['response_headers' => ['content-type' => 'application/json']]));

        try {
            $this->client($bad)->activate($key, 'example.com');
        } catch (PackageRejected) {
            // expected
        }

        $dump = $this->logger->dump();

        self::assertNotSame('', $dump, 'the operation should still be traceable');
        self::assertStringNotContainsString($key, $dump, 'no licence key');
        self::assertStringNotContainsString(hash('sha256', $key), $dump, 'no key fingerprint');
        self::assertStringNotContainsString(md5($this->factory->bytes($document)), $dump, 'no digest');
        self::assertStringNotContainsString('license_payload_b64', $dump, 'no payload');
        self::assertStringNotContainsString('BEGIN', $dump);

        foreach (['nonce', 'signature', 'license_md5', 'request_body', 'response_body', 'request_sha256', 'response_sha256'] as $forbidden) {
            self::assertNotContains($forbidden, $this->logger->contextKeys());
            self::assertStringNotContainsString($forbidden, $dump);
        }

        self::assertContains('op', $this->logger->contextKeys());
        self::assertContains('result', $this->logger->contextKeys());
    }

    private function client(MockHttpClient $http): ExchangeClient
    {
        return new ExchangeClient(
            $http,
            new ServiceEndpoints(),
            new SealedPackageReader($this->factory->anchors()),
            $this->logger,
        );
    }
}
