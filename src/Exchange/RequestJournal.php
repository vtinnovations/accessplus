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

namespace VTInnovations\AccessPlus\Exchange;

/**
 * Replay and idempotency memory for inbound service callbacks.
 *
 * Stored per request id: a digest of the nonce, a digest of the authenticated
 * raw body, the applied version, the outcome and the processing time. Digests,
 * never the values — this is private state, and it still has no reason to hold
 * anything reversible.
 *
 * Semantics the endpoint relies on:
 *   - the exact same request id with the exact same body is answered
 *     `already_processed` and applied only once;
 *   - the same request id with different content is a security event;
 *   - a nonce is single-use;
 *   - records outlive the retry window and are pruned with a bound.
 *
 * Single-node file store, guarded by an exclusive lock. Clustered installations
 * that share var/ over a POSIX-locking filesystem inherit the same guarantee;
 * anything else needs a shared transactional store (documented as a deployment
 * requirement).
 */
final class RequestJournal
{
    private const RELATIVE_PATH = 'var/accessplus/exchange/journal.json';

    private const RETENTION = 2592000; // 30 days

    private const MAX_ENTRIES = 1000;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{nonce: string, body: string, version: int, result: string, at: int}|null
     */
    public function find(string $requestId): ?array
    {
        $data = $this->read();
        $entry = $data['entries'][$requestId] ?? null;

        return \is_array($entry) ? $this->shape($entry) : null;
    }

    public function nonceSeen(string $nonceDigest): bool
    {
        $data = $this->read();

        return isset($data['nonces'][$nonceDigest]);
    }

    /**
     * Records an outcome. Called after a successful application and after a
     * rejection that must not be retried with the same identifiers.
     */
    public function record(string $requestId, string $nonceDigest, string $bodyDigest, int $version, string $result): void
    {
        $path = $this->path();
        $this->ensureDir(\dirname($path));

        $handle = @fopen($path, 'c+');

        if ($handle === false) {
            return;
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                return;
            }

            $raw = stream_get_contents($handle);
            $data = $this->decode(\is_string($raw) ? $raw : '');

            $data['entries'][$requestId] = [
                'nonce' => $nonceDigest,
                'body' => $bodyDigest,
                'version' => $version,
                'result' => $result,
                'at' => time(),
            ];
            $data['nonces'][$nonceDigest] = time();

            $data = $this->prune($data);

            $json = json_encode($data, JSON_UNESCAPED_SLASHES);

            if (\is_string($json)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $json);
                fflush($handle);
            }
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
            @chmod($path, 0600);
        }
    }

    public static function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, nonces: array<string, int>}
     */
    private function read(): array
    {
        $path = $this->path();

        if (!is_file($path)) {
            return ['entries' => [], 'nonces' => []];
        }

        $raw = @file_get_contents($path);

        return $this->decode(\is_string($raw) ? $raw : '');
    }

    /**
     * @return array{entries: array<string, array<string, mixed>>, nonces: array<string, int>}
     */
    private function decode(string $raw): array
    {
        if ($raw === '') {
            return ['entries' => [], 'nonces' => []];
        }

        $data = json_decode($raw, true);

        if (!\is_array($data)) {
            return ['entries' => [], 'nonces' => []];
        }

        return [
            'entries' => \is_array($data['entries'] ?? null) ? $data['entries'] : [],
            'nonces' => \is_array($data['nonces'] ?? null) ? $data['nonces'] : [],
        ];
    }

    /**
     * @param array{entries: array<string, array<string, mixed>>, nonces: array<string, int>} $data
     *
     * @return array{entries: array<string, array<string, mixed>>, nonces: array<string, int>}
     */
    private function prune(array $data): array
    {
        $cutoff = time() - self::RETENTION;

        foreach ($data['entries'] as $id => $entry) {
            if ((int) ($entry['at'] ?? 0) < $cutoff) {
                unset($data['entries'][$id]);
            }
        }

        foreach ($data['nonces'] as $digest => $at) {
            if ((int) $at < $cutoff) {
                unset($data['nonces'][$digest]);
            }
        }

        if (\count($data['entries']) > self::MAX_ENTRIES) {
            uasort($data['entries'], static fn (array $a, array $b): int => (int) ($b['at'] ?? 0) <=> (int) ($a['at'] ?? 0));
            $data['entries'] = \array_slice($data['entries'], 0, self::MAX_ENTRIES, true);
        }

        if (\count($data['nonces']) > self::MAX_ENTRIES * 2) {
            arsort($data['nonces']);
            $data['nonces'] = \array_slice($data['nonces'], 0, self::MAX_ENTRIES * 2, true);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{nonce: string, body: string, version: int, result: string, at: int}
     */
    private function shape(array $entry): array
    {
        return [
            'nonce' => (string) ($entry['nonce'] ?? ''),
            'body' => (string) ($entry['body'] ?? ''),
            'version' => (int) ($entry['version'] ?? 0),
            'result' => (string) ($entry['result'] ?? ''),
            'at' => (int) ($entry['at'] ?? 0),
        ];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the private exchange directory.');
        }
    }

    private function path(): string
    {
        return $this->projectDir . '/' . self::RELATIVE_PATH;
    }
}
