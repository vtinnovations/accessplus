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
use VTInnovations\AccessPlus\Exchange\RequestJournal;

final class RequestJournalTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/accessplus-journal-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            exec('rm -rf ' . escapeshellarg($this->projectDir));
        }
    }

    public function testAnUnknownRequestIsNotFound(): void
    {
        $journal = new RequestJournal($this->projectDir);

        self::assertNull($journal->find('req-1'));
        self::assertFalse($journal->nonceSeen(RequestJournal::digest('nonce-1')));
    }

    public function testARecordedRequestIsFoundAndItsNonceIsSpent(): void
    {
        $journal = new RequestJournal($this->projectDir);
        $journal->record('req-1', RequestJournal::digest('nonce-1'), RequestJournal::digest('body'), 9, 'updated');

        $entry = $journal->find('req-1');

        self::assertNotNull($entry);
        self::assertSame(9, $entry['version']);
        self::assertSame('updated', $entry['result']);
        self::assertSame(RequestJournal::digest('body'), $entry['body']);
        self::assertTrue($journal->nonceSeen(RequestJournal::digest('nonce-1')));
    }

    public function testOnlyDigestsArePersisted(): void
    {
        $journal = new RequestJournal($this->projectDir);
        $journal->record('req-1', RequestJournal::digest('secret-nonce'), RequestJournal::digest('secret-body'), 9, 'updated');

        $raw = (string) file_get_contents($this->projectDir . '/var/accessplus/exchange/journal.json');

        self::assertStringNotContainsString('secret-nonce', $raw);
        self::assertStringNotContainsString('secret-body', $raw);
        self::assertStringContainsString(RequestJournal::digest('secret-nonce'), $raw);
    }

    public function testTheJournalSurvivesANewInstance(): void
    {
        (new RequestJournal($this->projectDir))->record('req-1', RequestJournal::digest('n'), RequestJournal::digest('b'), 9, 'updated');

        self::assertNotNull((new RequestJournal($this->projectDir))->find('req-1'));
    }
}
