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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\State\DomainInventory;
use VTInnovations\AccessPlus\State\RootScope;

/**
 * Exact-host policy. Every "helpful" broadening an ordinary domain helper would
 * do — apex/www folding, eTLD+1 reduction, suffix matching, wildcards — is a
 * licence bypass here, so each one is asserted to fail.
 */
final class DomainInventoryTest extends TestCase
{
    public function testRepresentationIsNormalizedWithoutChangingTheHost(): void
    {
        $inventory = $this->inventory();

        self::assertSame('example.com', $inventory->normalize('Example.COM'));
        self::assertSame('example.com', $inventory->normalize('example.com.'));
        self::assertSame('example.com', $inventory->normalize('example.com:8443'));
        self::assertSame('example.com', $inventory->normalize('  example.com  '));
        self::assertSame('example.com', $inventory->normalize('https://example.com/path'));
    }

    public function testDistinctHostsStayDistinct(): void
    {
        $inventory = $this->inventory();

        self::assertNotSame($inventory->normalize('www.example.com'), $inventory->normalize('example.com'));
        self::assertNotSame($inventory->normalize('shop.example.com'), $inventory->normalize('example.com'));
        self::assertNotSame(
            $inventory->normalize('admin.shop.example.com'),
            $inventory->normalize('shop.example.com'),
        );
    }

    public function testUnusableValuesAreRejected(): void
    {
        $inventory = $this->inventory();

        self::assertNull($inventory->normalize('*.example.com'));
        self::assertNull($inventory->normalize(''));
        self::assertNull($inventory->normalize('192.0.2.10'));
        self::assertNull($inventory->normalize('[2001:db8::1]'));
        self::assertNull($inventory->normalize('user@example.com'));
        self::assertNull($inventory->normalize('example .com'));
        self::assertNull($inventory->normalize('-example.com'));
    }

    public function testIdnIsConvertedConsistently(): void
    {
        if (!\function_exists('idn_to_ascii')) {
            self::markTestSkipped('ext-intl is not available.');
        }

        self::assertSame('xn--mnchen-3ya.example', $this->inventory()->normalize('münchen.example'));
    }

    public function testSignedSetMustBeCanonicalSortedAndUnique(): void
    {
        $inventory = $this->inventory();

        self::assertSame(
            ['a.example.com', 'b.example.com'],
            $inventory->acceptSignedSet(['a.example.com', 'b.example.com']),
        );

        self::assertNull($inventory->acceptSignedSet([]), 'empty');
        self::assertNull($inventory->acceptSignedSet(['b.example.com', 'a.example.com']), 'unsorted');
        self::assertNull($inventory->acceptSignedSet(['a.example.com', 'a.example.com']), 'duplicate');
        self::assertNull($inventory->acceptSignedSet(['*.example.com']), 'wildcard');
        self::assertNull($inventory->acceptSignedSet(['Example.com']), 'not canonical');
        self::assertNull($inventory->acceptSignedSet(['example.com.']), 'not canonical');
        self::assertNull($inventory->acceptSignedSet('example.com'), 'not a list');
    }

    public function testInventoryComesFromTheRootConfigurationOnly(): void
    {
        $inventory = $this->inventory([
            ['id' => 5, 'dns' => 'Example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
            ['id' => 6, 'dns' => '', 'language' => 'en', 'title' => 'B', 'useSSL' => 1],
        ], 'evil.example.net');

        self::assertSame(['example.com'], $inventory->forRoot(5));
        self::assertSame([], $inventory->forRoot(6), 'A root without a domain is not licensable.');
        self::assertSame([], $inventory->forRoot(999));

        // The request host is not part of the inventory, so it cannot be used.
        self::assertSame('example.com', $inventory->verificationHost(5));
        self::assertNull($inventory->verificationHost(6));
    }

    public function testCurrentHostIsPreferredOnlyWhenItIsConfigured(): void
    {
        $inventory = $this->inventory(
            [['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1]],
            'example.com',
        );

        self::assertSame('example.com', $inventory->verificationHost(5));
    }

    public function testIntersectionIsExactAndDeterministic(): void
    {
        $inventory = $this->inventory([['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1]]);

        self::assertSame(['example.com'], $inventory->intersect(5, ['example.com', 'other.example.com']));
        self::assertSame([], $inventory->intersect(5, ['www.example.com']), 'www is a different host');
        self::assertSame([], $inventory->intersect(5, ['shop.example.com']), 'subdomains are different hosts');
        self::assertSame([], $inventory->intersect(5, []));
    }

    public function testRootLookupUsesExactMatchOnly(): void
    {
        $inventory = $this->inventory([
            ['id' => 5, 'dns' => 'example.com', 'language' => 'de', 'title' => 'A', 'useSSL' => 1],
            ['id' => 6, 'dns' => 'www.example.com', 'language' => 'de', 'title' => 'B', 'useSSL' => 1],
        ]);

        self::assertSame(5, $inventory->rootForHost('example.com'));
        self::assertSame(6, $inventory->rootForHost('www.example.com'));
        self::assertSame(0, $inventory->rootForHost('deep.www.example.com'));
        self::assertSame(0, $inventory->rootForHost('example.com.evil.test'));
    }

    /**
     * @param list<array<string, mixed>> $roots
     */
    private function inventory(array $roots = [], ?string $currentHost = null): DomainInventory
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($roots);

        $stack = new RequestStack();

        if ($currentHost !== null) {
            $stack->push(Request::create('https://' . $currentHost . '/'));
        }

        return new DomainInventory(new RootScope($connection), $stack);
    }
}
