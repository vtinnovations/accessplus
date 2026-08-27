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

namespace VTInnovations\AccessPlus\Alt;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Writes an approved alt text into the canonical store, tl_files.meta — and ONLY
 * into an empty slot for that language. It never overwrites or appends to an
 * existing manual alt, and it preserves all other meta fields/languages
 * (the project guidelines §5). For a decorative image the written value is '' (a valid,
 * intentional empty alt).
 */
final class MetaWriter
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function writeAlt(string $fileUuid, string $lang, string $alt): MetaWriteResult
    {
        $bin = StringUtil::uuidToBin($fileUuid);

        $serialized = $this->connection->fetchOne(
            'SELECT meta FROM tl_files WHERE uuid = ?',
            [$bin],
        );

        if ($serialized === false) {
            return MetaWriteResult::NotFound;
        }

        $meta = StringUtil::deserialize(\is_string($serialized) ? $serialized : '', true);
        if (!\is_array($meta)) {
            $meta = [];
        }

        $existing = $meta[$lang]['alt'] ?? null;
        if (\is_string($existing) && trim($existing) !== '') {
            return MetaWriteResult::SkippedManual;
        }

        // Preserve any existing per-language fields (title, caption, link, …).
        $langMeta = \is_array($meta[$lang] ?? null) ? $meta[$lang] : [];
        $langMeta['alt'] = $alt;
        $meta[$lang] = $langMeta;

        $this->connection->update(
            'tl_files',
            ['meta' => serialize($meta)],
            ['uuid' => $bin],
        );

        return MetaWriteResult::Written;
    }

    /**
     * Reads the current alt for a language. `absent` distinguishes "no alt key
     * at all" from an explicitly empty alt — needed to undo precisely.
     *
     * @return array{value: ?string, absent: bool}
     */
    public function readAlt(string $fileUuid, string $lang): array
    {
        $serialized = $this->connection->fetchOne('SELECT meta FROM tl_files WHERE uuid = ?', [StringUtil::uuidToBin($fileUuid)]);
        if ($serialized === false) {
            return ['value' => null, 'absent' => true];
        }

        $meta = StringUtil::deserialize(\is_string($serialized) ? $serialized : '', true);
        if (!\is_array($meta) || !\is_array($meta[$lang] ?? null) || !\array_key_exists('alt', $meta[$lang])) {
            return ['value' => null, 'absent' => true];
        }

        return ['value' => (string) $meta[$lang]['alt'], 'absent' => false];
    }

    /**
     * Reverts an alt to its previous state — but ONLY if the current value still
     * equals what we wrote ($expectedCurrent). If a human edited it since, we do
     * not clobber that and return false.
     */
    public function restoreAlt(string $fileUuid, string $lang, string $expectedCurrent, ?string $previous, bool $previousAbsent): bool
    {
        $bin = StringUtil::uuidToBin($fileUuid);

        $serialized = $this->connection->fetchOne('SELECT meta FROM tl_files WHERE uuid = ?', [$bin]);
        if ($serialized === false) {
            return false;
        }

        $meta = StringUtil::deserialize(\is_string($serialized) ? $serialized : '', true);
        if (!\is_array($meta) || !\is_array($meta[$lang] ?? null) || !\array_key_exists('alt', $meta[$lang])) {
            return false;
        }

        if ((string) $meta[$lang]['alt'] !== $expectedCurrent) {
            return false; // changed since — never clobber a manual edit
        }

        if ($previousAbsent) {
            unset($meta[$lang]['alt']);
        } else {
            $meta[$lang]['alt'] = (string) $previous;
        }

        $this->connection->update('tl_files', ['meta' => serialize($meta)], ['uuid' => $bin]);

        return true;
    }
}
