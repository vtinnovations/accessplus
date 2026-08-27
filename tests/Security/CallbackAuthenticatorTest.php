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
use Symfony\Component\HttpFoundation\Request;
use VTInnovations\AccessPlus\Exchange\ServiceEndpoints;
use VTInnovations\AccessPlus\Security\CallbackAuthenticator;
use VTInnovations\AccessPlus\Security\CanonicalForm;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\Tests\Support\TestPackageFactory;

final class CallbackAuthenticatorTest extends TestCase
{
    private TestPackageFactory $factory;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('libsodium is not available in this runtime.');
        }

        $this->factory = new TestPackageFactory();
    }

    public function testAValidSignatureAuthenticates(): void
    {
        $meta = $this->authenticator()->authenticate(...$this->request('{"a":1}'));

        self::assertSame('req-1', $meta['request_id']);
        self::assertSame('test-key', $meta['key_id']);
    }

    public function testARequestOnAnotherPathIsRejected(): void
    {
        [$request, $body, ] = $this->request('{"a":1}');

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('callback_path_mismatch');

        // The handler always passes the one path this endpoint is served at.
        $this->authenticator()->authenticate($request, $body, '/some/other/path');
    }

    public function testASignatureMadeForAnotherPathIsRejected(): void
    {
        [$request, $body, $path] = $this->request('{"a":1}', '/rest/api/v1/other-license-updater');

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('callback_signature_invalid');

        $this->authenticator()->authenticate($request, $body, $path);
    }

    public function testASignatureForAnotherBodyIsRejected(): void
    {
        [$request, , $path] = $this->request('{"a":1}');

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('callback_signature_invalid');

        $this->authenticator()->authenticate($request, '{"a":2}', $path);
    }

    public function testAnUnknownKeyIdIsRejectedEvenWithAValidSignature(): void
    {
        [$request, $body, $path] = $this->request('{"a":1}', null, 'rotated-out-key');

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('unknown_signing_key');

        $this->authenticator()->authenticate($request, $body, $path);
    }

    public function testMissingHeadersAreRejectedBeforeAnyCryptography(): void
    {
        $request = Request::create(ServiceEndpoints::CALLBACK_PATH, 'POST', [], [], [], [], '{}');

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('callback_unsigned');

        $this->authenticator()->authenticate($request, '{}', ServiceEndpoints::CALLBACK_PATH);
    }

    /**
     * @return array{0: Request, 1: string, 2: string}
     */
    private function request(string $body, ?string $signedPath = null, string $keyId = 'test-key'): array
    {
        $path = ServiceEndpoints::CALLBACK_PATH;
        $timestamp = time();

        $message = CanonicalForm::request('POST', $signedPath ?? $path, 'req-1', $timestamp, 'nonce-1', CanonicalForm::bodyHash($body));

        $request = Request::create($path, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_VT_REQUEST_ID' => 'req-1',
            'HTTP_X_VT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_VT_NONCE' => 'nonce-1',
            'HTTP_X_VT_KEY_ID' => $keyId,
            'HTTP_X_VT_SIGNATURE' => $this->factory->sign($message),
        ], $body);

        return [$request, $body, $path];
    }

    private function authenticator(): CallbackAuthenticator
    {
        return new CallbackAuthenticator($this->factory->anchors());
    }
}
