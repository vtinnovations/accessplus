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

namespace VTInnovations\AccessPlus\State;

/**
 * Authoritative, persisted, per-root counters for the Demo package's cumulative
 * allowances (ALT images generated, frontend pages scanned, axe issues shown).
 *
 * This is the ONLY place that may increment those counters. Every caller must
 * go through {@see tryConsume()} or {@see reserveUpTo()} at the moment the
 * corresponding chargeable operation actually proceeds — never merely to show a
 * form or render a count.
 *
 * Persistence and concurrency guarantees, deliberately mirroring
 * {@see RegistrationStore}'s private var/ area and locking discipline:
 *
 *   - one JSON file per root under the bundle's own private state directory,
 *     outside the document root and already git-ignored;
 *   - every read-check-increment-write cycle happens while holding an exclusive
 *     lock on a companion `.lock` file for that root, so two concurrent PHP
 *     workers (two browser tabs, a cron overlapping a manual click, two nodes
 *     sharing the filesystem) cannot both observe "24 used" and both commit the
 *     25th and 26th unit — the cap is enforced inside the critical section, not
 *     read-then-checked-then-written across separate calls;
 *   - the counter is keyed by root id alone, never by the stored licence key,
 *     session id or request. It therefore survives logout/login, session
 *     expiry, cache clears, and re-entering the same (or a different) key —
 *     the only way it moves is a successful chargeable operation, and the only
 *     way it goes away is removing the bundle's own private state for that root.
 *
 * What is honestly NOT covered: a multi-node deployment whose var/ directories
 * are NOT shared over a POSIX-locking filesystem has no shared lock and can
 * therefore over-count across nodes (documented deployment requirement, same
 * caveat as {@see \VTInnovations\AccessPlus\Exchange\RequestJournal}). An
 * administrator with filesystem access can still delete this file by hand —
 * that is a local-trust boundary this store does not attempt to cross, exactly
 * like every other file under var/.
 */
final class UsageLedger
{
    private const BASE_RELATIVE_PATH = 'var/accessplus/roots';

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * Atomically reserves exactly $amount units of $bucket for $rootId if doing
     * so would not exceed $cap. Returns whether the reservation succeeded; on
     * false, NOTHING is written — the counter is left exactly as it was.
     */
    public function tryConsume(int $rootId, string $bucket, int $cap, int $amount = 1): bool
    {
        return $this->reserveUpTo($rootId, $bucket, $cap, $amount) === $amount;
    }

    /**
     * Atomically reserves as many units of $bucket for $rootId as fit under
     * $cap, up to $requested. Returns the amount actually granted (0..$requested).
     *
     * Used for the axe-issue truncation, where a single page's finding batch
     * must be trimmed to exactly however many units remain of the 30-issue
     * allowance rather than being accepted or rejected as a whole.
     */
    public function reserveUpTo(int $rootId, string $bucket, int $cap, int $requested): int
    {
        // Root id 0 is a deliberate, valid scope: the install-wide bucket used
        // by a feature (ALT generation) that is not bound to one site root,
        // the same "whole install" convention {@see \VTInnovations\AccessPlus\Check\LintRunner}
        // and {@see \VTInnovations\AccessPlus\Frontend\FrontendFindingStore} already
        // use for root-independent data. Only a genuinely negative id is invalid.
        if ($rootId < 0 || $requested <= 0 || $cap <= 0 || $bucket === '') {
            return 0;
        }

        $dir = $this->rootDir($rootId);
        $this->ensureDir($dir);

        $lock = $this->acquireLock($dir);

        try {
            $data = $this->readLocked($dir);
            $used = (int) ($data[$bucket] ?? 0);

            $remaining = max(0, $cap - $used);
            $grant = min($requested, $remaining);

            if ($grant > 0) {
                $data[$bucket] = $used + $grant;
                $this->writeLocked($dir, $data);
            }

            return $grant;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Current cumulative usage of $bucket for $rootId. Read-only, unlocked
     * (a momentarily stale count under concurrent writes is fine for display;
     * every enforcement decision goes through the locked methods above).
     */
    public function used(int $rootId, string $bucket): int
    {
        if ($rootId < 0) {
            return 0;
        }

        return (int) ($this->readUnlocked($rootId)[$bucket] ?? 0);
    }

    public function remaining(int $rootId, string $bucket, int $cap): int
    {
        return max(0, $cap - $this->used($rootId, $bucket));
    }

    /**
     * @return array<string, int>
     */
    private function readUnlocked(int $rootId): array
    {
        $path = $this->rootDir($rootId) . '/usage.json';

        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);

        return $this->decode(\is_string($raw) ? $raw : '');
    }

    /**
     * @return array<string, int>
     */
    private function readLocked(string $dir): array
    {
        $raw = @file_get_contents($dir . '/usage.json');

        return $this->decode(\is_string($raw) ? $raw : '');
    }

    /**
     * @param array<string, int> $data
     */
    private function writeLocked(string $dir, array $data): void
    {
        $path = $dir . '/usage.json';
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        if (!\is_string($json)) {
            return;
        }

        if (@file_put_contents($tmp, $json) === false) {
            return;
        }

        @chmod($tmp, 0600);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    /**
     * @return array<string, int>
     */
    private function decode(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (!\is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $bucket => $value) {
            if (\is_string($bucket) && \is_int($value) && $value >= 0) {
                $out[$bucket] = $value;
            }
        }

        return $out;
    }

    /**
     * @return resource
     */
    private function acquireLock(string $dir)
    {
        $handle = @fopen($dir . '/usage.lock', 'c');

        if ($handle === false) {
            throw new \RuntimeException('Could not open the usage lock.');
        }

        if (!@flock($handle, LOCK_EX)) {
            @fclose($handle);

            throw new \RuntimeException('Could not acquire the usage lock.');
        }

        @chmod($dir . '/usage.lock', 0600);

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the private state directory.');
        }
    }

    private function rootDir(int $rootId): string
    {
        return $this->projectDir . '/' . self::BASE_RELATIVE_PATH . '/' . $rootId;
    }
}
