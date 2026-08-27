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

namespace VTInnovations\AccessPlus\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Records everything written to the logger so the tests can assert that no
 * packet content, key, digest, signature or nonce ever reaches ordinary logs.
 */
final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param mixed             $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Everything that was logged, flattened into one searchable string.
     */
    public function dump(): string
    {
        $out = '';

        foreach ($this->records as $record) {
            $out .= $record['level'] . ' ' . $record['message'] . ' '
                . json_encode($record['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function contextKeys(): array
    {
        $keys = [];

        foreach ($this->records as $record) {
            foreach (array_keys($record['context']) as $key) {
                $keys[] = (string) $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
