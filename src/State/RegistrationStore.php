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
 * The ONE authoritative store for a root's registration state.
 *
 * Design notes that matter for correctness:
 *
 *  - The exact received bytes and the envelope that pins them live in a SINGLE
 *    file, so activating a new state is one atomic rename. Two files could be
 *    renamed only one at a time, which is exactly how a crash produces bytes and
 *    a digest that disagree.
 *  - The bytes are kept Base64-encoded inside that container so byte-for-byte
 *    fidelity survives the round trip; they are never re-serialized from a
 *    parsed structure, which would invalidate the digest and signature.
 *  - This class does no crypto. The caller passes a verifier closure that is run
 *    against the temporary copy AND again against the activated copy, and any
 *    failure rolls the previous state back. Keeping verification out of the
 *    persistence layer means neither file alone tells the whole story.
 *  - The directory is the bundle's existing private state area under var/, which
 *    is outside the document root and already git-ignored. No path ever comes
 *    from a request.
 */
final class RegistrationStore
{
    private const BASE_RELATIVE_PATH = 'var/accessplus/roots';

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{bytes: string, envelope: array<string, mixed>}|null
     */
    public function read(int $rootId): ?array
    {
        return $this->readFile($this->statePath($rootId));
    }

    public function has(int $rootId): bool
    {
        return is_file($this->statePath($rootId));
    }

    /**
     * The durable tombstone for this root: the exact bytes of the last authentic
     * negative state (revocation / expiry) that was ever applied. It is written
     * next to the state file, deliberately NOT removed by {@see remove()} and
     * NOT overwritten by a lower version, so restoring an old valid state file
     * from a backup cannot resurrect a revoked root.
     *
     * @return array{bytes: string, envelope: array<string, mixed>, version: int}|null
     */
    public function readTombstone(int $rootId): ?array
    {
        $file = $this->readFile($this->tombstonePath($rootId));

        if ($file === null) {
            return null;
        }

        return $file + ['version' => (int) ($file['envelope']['license_version'] ?? 0)];
    }

    /**
     * Atomically replaces the state of one root.
     *
     * @param array<string, mixed> $envelope
     * @param \Closure(string, array<string, mixed>): void $verify   throws to veto
     * @param bool                                         $tombstone the document being committed is an authentic
     *                                                                negative state (revocation/expiry); persist it as the
     *                                                                durable tombstone in addition to the live state
     *
     * @throws \RuntimeException on any I/O problem; the previous state survives
     */
    public function commit(int $rootId, string $bytes, array $envelope, \Closure $verify, bool $tombstone = false): void
    {
        if ($rootId <= 0) {
            throw new \RuntimeException('Invalid scope for registration state.');
        }

        $dir = $this->rootDir($rootId);
        $this->ensureDir($dir);

        $lock = $this->acquireLock($dir);

        try {
            // 0. A committed state — positive or negative — can never be older
            //    than, or equal to, the durable tombstone version.
            $incomingVersion = (int) ($envelope['license_version'] ?? 0);
            $existingTombstone = $this->readFile($this->tombstonePath($rootId));

            if ($existingTombstone !== null
                && $incomingVersion <= (int) ($existingTombstone['envelope']['license_version'] ?? 0)
            ) {
                throw new \RuntimeException('Registration state is not newer than the tombstone.');
            }

            // 1. Never write anything that does not verify.
            $verify($bytes, $envelope);

            $container = json_encode(
                ['payload_b64' => base64_encode($bytes), 'integrity' => $envelope, 'stored_at' => time()],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            if (!\is_string($container)) {
                throw new \RuntimeException('Could not encode registration state.');
            }

            $path = $this->statePath($rootId);
            $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

            // 2. Same-filesystem temporary file, flushed to disk.
            $handle = @fopen($tmp, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Could not stage registration state.');
            }

            try {
                if (@fwrite($handle, $container) === false) {
                    throw new \RuntimeException('Could not stage registration state.');
                }
                @fflush($handle);
            } finally {
                @fclose($handle);
            }

            @chmod($tmp, 0600);

            // 3. Re-read and verify the staged copy before it becomes active.
            //    Anything wrong with it dies here, with the temporary file gone
            //    and the currently active state never touched.
            try {
                $staged = $this->readFile($tmp);

                if ($staged === null) {
                    throw new \RuntimeException('Staged registration state is unreadable.');
                }

                $verify($staged['bytes'], $staged['envelope']);
            } catch (\Throwable $e) {
                @unlink($tmp);

                throw $e;
            }

            // 4. Keep the previous state as rollback material.
            $backup = null;
            if (is_file($path)) {
                $backup = $path . '.' . bin2hex(random_bytes(6)) . '.bak';
                if (!@copy($path, $backup)) {
                    @unlink($tmp);

                    throw new \RuntimeException('Could not back up registration state.');
                }
            }

            // 5. Single atomic activation.
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                if ($backup !== null) {
                    @unlink($backup);
                }

                throw new \RuntimeException('Could not activate registration state.');
            }

            // 6. Verify what is now actually active; roll back on any doubt.
            try {
                $active = $this->readFile($path);
                if ($active === null) {
                    throw new \RuntimeException('Activated registration state is unreadable.');
                }
                $verify($active['bytes'], $active['envelope']);
            } catch (\Throwable $e) {
                if ($backup !== null) {
                    @rename($backup, $path);
                } else {
                    @unlink($path);
                }

                throw new \RuntimeException('Registration state rolled back.', 0, $e);
            }

            if ($backup !== null) {
                @unlink($backup);
            }

            // 7. Persist the durable tombstone for an authentic negative state.
            //    Same container format, same atomic rename. It is written last,
            //    after the live state is proven good, and it is only ever moved
            //    forward (the version guard above already rejected a lower one).
            if ($tombstone) {
                $this->writeContainer($this->tombstonePath($rootId), $bytes, $envelope);
            }
        } finally {
            $this->releaseLock($lock);
            $this->sweep($dir);
        }
    }

    /**
     * Atomic single-file container write (temp file + rename on the same
     * filesystem). Used for the tombstone; the live state uses the fuller
     * staged/activated/verified path above.
     *
     * @param array<string, mixed> $envelope
     */
    private function writeContainer(string $path, string $bytes, array $envelope): void
    {
        $container = json_encode(
            ['payload_b64' => base64_encode($bytes), 'integrity' => $envelope, 'stored_at' => time()],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (!\is_string($container)) {
            throw new \RuntimeException('Could not encode registration state.');
        }

        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($tmp, $container) === false) {
            throw new \RuntimeException('Could not stage registration state.');
        }

        @chmod($tmp, 0600);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            throw new \RuntimeException('Could not activate registration state.');
        }
    }

    /**
     * Removes the state of one root under the same lock. Only this bundle's own
     * private files are touched; nothing else on the site is changed.
     */
    public function remove(int $rootId): void
    {
        if ($rootId <= 0) {
            return;
        }

        $dir = $this->rootDir($rootId);

        if (!is_dir($dir)) {
            return;
        }

        $lock = $this->acquireLock($dir);

        try {
            // The live state goes; the durable tombstone stays. "Remove licence"
            // returns the root to Contao's default behaviour, but it must not be
            // a way to shed an authentic revocation.
            @unlink($this->statePath($rootId));
            $this->sweep($dir);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array{bytes: string, envelope: array<string, mixed>}|null
     */
    private function readFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data) || !\is_string($data['payload_b64'] ?? null) || !\is_array($data['integrity'] ?? null)) {
            return null;
        }

        $bytes = base64_decode($data['payload_b64'], true);

        if (!\is_string($bytes) || $bytes === '' || base64_encode($bytes) !== $data['payload_b64']) {
            return null;
        }

        /** @var array<string, mixed> $envelope */
        $envelope = $data['integrity'];

        return ['bytes' => $bytes, 'envelope' => $envelope];
    }

    /**
     * @return resource
     */
    private function acquireLock(string $dir)
    {
        $handle = @fopen($dir . '/.lock', 'c');

        if ($handle === false) {
            throw new \RuntimeException('Could not open the registration lock.');
        }

        if (!@flock($handle, LOCK_EX)) {
            @fclose($handle);

            throw new \RuntimeException('Could not acquire the registration lock.');
        }

        @chmod($dir . '/.lock', 0600);

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

    /**
     * Removes stale temporary/backup files left behind by a crashed writer.
     */
    private function sweep(string $dir): void
    {
        foreach (['state.json.*', 'tombstone.json.*'] as $pattern) {
            foreach (glob($dir . '/' . $pattern) ?: [] as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    @unlink($file);
                }
            }
        }
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

    private function statePath(int $rootId): string
    {
        return $this->rootDir($rootId) . '/state.json';
    }

    private function tombstonePath(int $rootId): string
    {
        return $this->rootDir($rootId) . '/tombstone.json';
    }
}
