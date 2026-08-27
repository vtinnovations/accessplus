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
use VTInnovations\AccessPlus\Security\CanonicalForm;

/**
 * The canonical byte forms are a cross-system contract: if these change, every
 * signature made by the vendor stops verifying. They are therefore asserted
 * against literal expected strings, not against another implementation.
 */
final class CanonicalFormTest extends TestCase
{
    public function testMembersAreSortedBytewiseAndSignatureIsRemoved(): void
    {
        $document = [
            'b' => 2,
            'a' => 1,
            'signature' => 'must-not-appear',
            'A' => 0,
        ];

        self::assertSame('{"A":0,"a":1,"b":2}', CanonicalForm::document($document));
    }

    public function testListOrderIsPreservedAndNestedObjectsAreSorted(): void
    {
        $document = [
            'license_domains' => ['b.example.com', 'a.example.com'],
            'nested' => ['z' => 1, 'y' => ['n' => 2, 'm' => 1]],
        ];

        self::assertSame(
            '{"license_domains":["b.example.com","a.example.com"],"nested":{"y":{"m":1,"n":2},"z":1}}',
            CanonicalForm::document($document),
        );
    }

    public function testSlashesAndUnicodeAreNotEscapedAndScalarTypesSurvive(): void
    {
        $document = [
            'url' => 'https://www.example.com/a/b',
            'name' => 'Grüße',
            'flag' => false,
            'nothing' => null,
            'zero' => 0,
        ];

        self::assertSame(
            '{"flag":false,"name":"Grüße","nothing":null,"url":"https://www.example.com/a/b","zero":0}',
            CanonicalForm::document($document),
        );
    }

    public function testRequestMessageHasSixLinesAndNoTrailingNewline(): void
    {
        $message = CanonicalForm::request('post', '/rest/api/v1/accessplus-license-updater', 'req-1', 1784880547, 'nonce-1', str_repeat('a', 64));

        self::assertSame(
            "POST\n/rest/api/v1/accessplus-license-updater\nreq-1\n1784880547\nnonce-1\n" . str_repeat('a', 64),
            $message,
        );
        self::assertSame(6, substr_count($message, "\n") + 1);
        self::assertStringEndsNotWith("\n", $message);
    }

    /**
     * The key id selects the key; it is deliberately NOT signed.
     */
    public function testRequestMessageDoesNotContainTheKeyId(): void
    {
        $message = CanonicalForm::request('POST', '/p', 'req', 1, 'n', 'h');

        self::assertStringNotContainsString('vtone-2026a', $message);
    }

    public function testStrictBase64RejectsNonCanonicalInput(): void
    {
        self::assertSame('hi', CanonicalForm::fromBase64('aGk='));
        self::assertNull(CanonicalForm::fromBase64('aGk'));       // missing padding
        self::assertNull(CanonicalForm::fromBase64("aGk=\n"));    // whitespace
        self::assertNull(CanonicalForm::fromBase64('!!!'));
        self::assertNull(CanonicalForm::fromBase64(''));
    }
}
