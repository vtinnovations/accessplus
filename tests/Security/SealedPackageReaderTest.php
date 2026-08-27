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
use VTInnovations\AccessPlus\Security\SealedPackageReader;
use VTInnovations\AccessPlus\Security\TrustAnchors;
use VTInnovations\AccessPlus\Tests\Support\TestPackageFactory;

/**
 * Exercises the real verification path: envelope signature first, then the
 * exact-byte digest, then the document signature.
 */
final class SealedPackageReaderTest extends TestCase
{
    private TestPackageFactory $factory;

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_sign_keypair')) {
            self::markTestSkipped('libsodium is not available in this runtime.');
        }

        $this->factory = new TestPackageFactory();
    }

    public function testValidPackageOpens(): void
    {
        $response = $this->factory->response($this->factory->document());
        $package = $this->reader()->open($response);

        self::assertSame(7, $package->version());
        self::assertSame('test-key', $package->keyId());
        self::assertSame('pro', $package->document['license_package']);
        self::assertSame(
            base64_decode($response['license_payload_b64'], true),
            $package->bytes,
            'The stored bytes must be exactly the received bytes.',
        );
    }

    public function testASingleChangedByteIsCaught(): void
    {
        $response = $this->factory->response($this->factory->document());
        $bytes = base64_decode($response['license_payload_b64'], true);

        // Whitespace only — semantically identical JSON, different bytes.
        $response['license_payload_b64'] = base64_encode(' ' . $bytes);

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('payload_digest_mismatch');

        $this->reader()->open($response);
    }

    public function testRecomputedDigestDoesNotRescueATamperedDocument(): void
    {
        $document = $this->factory->document();
        $bytes = $this->factory->bytes($document);

        // The classic attack: edit the document, recompute the MD5, keep the
        // envelope otherwise intact but unsigned for the new content.
        $tampered = str_replace('"license_max_domains":3', '"license_max_domains":9999', $bytes);
        self::assertNotSame($bytes, $tampered);

        $envelope = $this->factory->envelope($bytes);
        $envelope['license_md5'] = md5($tampered); // signature no longer covers this

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('envelope_signature_invalid');

        $this->reader()->open([
            'license_payload_b64' => base64_encode($tampered),
            'integrity' => $envelope,
        ]);
    }

    public function testDocumentSignedByAnotherKeyIsRejected(): void
    {
        $foreign = new TestPackageFactory();
        $document = $foreign->document();
        $bytes = $foreign->bytes($document);   // signed by the foreign key
        $envelope = $this->factory->envelope($bytes); // envelope by the trusted key

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('document_signature_invalid');

        $this->reader()->open(['license_payload_b64' => base64_encode($bytes), 'integrity' => $envelope]);
    }

    public function testNonStrictBase64IsRejected(): void
    {
        $response = $this->factory->response($this->factory->document());
        $response['license_payload_b64'] = "\n" . $response['license_payload_b64'];

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('malformed_payload_encoding');

        $this->reader()->open($response);
    }

    public function testUnknownEnvelopeKeyIdIsRejected(): void
    {
        $document = $this->factory->document();
        $bytes = $this->factory->bytes($document);
        $envelope = $this->factory->envelope($bytes, 7, 'someone-elses-key');

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('unknown_signing_key');

        $this->reader()->open(['license_payload_b64' => base64_encode($bytes), 'integrity' => $envelope]);
    }

    public function testEmptyKeyRingFailsClosed(): void
    {
        $response = $this->factory->response($this->factory->document());

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('signing_key_store_empty');

        (new SealedPackageReader(new TrustAnchors([])))->open($response);
    }

    public function testEnvelopeAndDocumentVersionMustAgree(): void
    {
        $document = $this->factory->document();
        $bytes = $this->factory->bytes($document);
        $envelope = $this->factory->envelope($bytes, 9);

        $this->expectException(PackageRejected::class);
        $this->expectExceptionMessage('envelope_document_mismatch');

        $this->reader()->open(['license_payload_b64' => base64_encode($bytes), 'integrity' => $envelope]);
    }

    public function testStoredStateIsVerifiedTheSameWayAsAResponse(): void
    {
        $document = $this->factory->document();
        $bytes = $this->factory->bytes($document);
        $envelope = $this->factory->envelope($bytes);

        self::assertSame(7, $this->reader()->reopen($bytes, $envelope)->version());

        $this->expectException(PackageRejected::class);
        $this->reader()->reopen(str_replace('"pro"', '"free"', $bytes), $envelope);
    }

    private function reader(): SealedPackageReader
    {
        return new SealedPackageReader($this->factory->anchors());
    }
}
