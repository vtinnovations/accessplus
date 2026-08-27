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

namespace VTInnovations\AccessPlus\Media;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Collects the UUIDs of every file actually USED on the site, across all common
 * Contao embedding mechanisms — not just content single-image elements:
 *
 *   - singleSRC  (content elements, modules, news, events): one binary UUID
 *   - multiSRC   (galleries, sliders, image modules): serialized UUID list
 *   - {{file::UUID}} insert tags inside rich-text fields
 *
 * This lets the alt-text checks/generator target only images that appear
 * somewhere, instead of every orphaned file in the Files manager.
 *
 * Scope note: third-party sliders that keep their own image table (and not a
 * Contao multiSRC field) are NOT covered here — those rendered <img> are caught
 * by the frontend axe scan instead. Identifiers in the SQL below are fixed
 * constants, never user input (the project guidelines §3.3).
 */
final class UsedImageCollector
{
    /** Tables/columns holding a single binary UUID. */
    private const SINGLE_SRC = [
        ['tl_content', 'singleSRC'],
        ['tl_module', 'singleSRC'],
        ['tl_news', 'singleSRC'],
        ['tl_calendar_events', 'singleSRC'],
    ];

    /** Tables/columns holding a serialized UUID list. */
    private const MULTI_SRC = [
        ['tl_content', 'multiSRC'],
        ['tl_content', 'orderSRC'],
        ['tl_module', 'multiSRC'],
    ];

    /** Rich-text columns that may carry {{file::UUID}} insert tags. */
    private const RTE = [
        ['tl_content', 'text'],
        ['tl_content', 'html'],
        ['tl_news', 'teaser'],
        ['tl_calendar_events', 'teaser'],
    ];

    /** @var array<string, true>|null Cached used-UUID set (string form). */
    private ?array $cache = null;

    /** @var list<string>|null */
    private ?array $tableNames = null;

    /** @var array<string, list<string>> */
    private array $columnCache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, true> Map of used file UUID (string form) => true.
     */
    public function usedUuids(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $used = [];

        foreach (self::SINGLE_SRC as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }

            $rows = $this->connection->fetchFirstColumn(
                'SELECT DISTINCT ' . $column . ' FROM ' . $table
                . ' WHERE ' . $column . ' IS NOT NULL AND LENGTH(' . $column . ') = 16',
            );
            foreach ($rows as $bin) {
                $uuid = $this->toUuid($bin);
                if ($uuid !== null) {
                    $used[$uuid] = true;
                }
            }
        }

        foreach (self::MULTI_SRC as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }

            $rows = $this->connection->fetchFirstColumn(
                'SELECT ' . $column . ' FROM ' . $table
                . ' WHERE ' . $column . " IS NOT NULL AND " . $column . " != ''",
            );
            foreach ($rows as $blob) {
                foreach ($this->expandMulti($blob) as $uuid) {
                    $used[$uuid] = true;
                }
            }
        }

        foreach (self::RTE as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }

            $rows = $this->connection->fetchFirstColumn(
                'SELECT ' . $column . ' FROM ' . $table
                . ' WHERE ' . $column . " LIKE '%{{file::%'",
            );
            foreach ($rows as $html) {
                foreach ($this->insertTagUuids((string) $html) as $uuid) {
                    $used[$uuid] = true;
                }
            }
        }

        // Catch images stored in ANY other column too — theme/custom/rsce image
        // fields (icon-box "Bild-Icon", teaser images, custom DCA fields) keep
        // their UUID outside singleSRC/multiSRC. Scan every column of the content
        // tables for binary/serialized/insert-tag UUIDs. Spurious values are
        // harmless: only UUIDs that match a real image in tl_files are ever used.
        foreach (['tl_content', 'tl_module'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($this->connection->fetchAllAssociative('SELECT * FROM ' . $table) as $row) {
                foreach ($row as $column => $value) {
                    if (\in_array($column, ['id', 'pid', 'tstamp', 'sorting'], true)) {
                        continue;
                    }
                    $this->collectUuids($value, $used);
                }
            }
        }

        return $this->cache = $used;
    }

    /**
     * Recursively pull file UUIDs out of any column value: a raw binary UUID, a
     * string UUID, a {{file::UUID}} insert tag, or a serialized blob of these.
     *
     * @param array<string, true> $out
     */
    private function collectUuids(mixed $value, array &$out): void
    {
        if (\is_array($value)) {
            foreach ($value as $v) {
                $this->collectUuids($v, $out);
            }

            return;
        }

        if (!\is_string($value) || $value === '') {
            return;
        }

        if (\strlen($value) === 16) {
            $uuid = $this->toUuid($value);
            if ($uuid !== null) {
                $out[$uuid] = true;
            }

            return;
        }

        if (str_contains($value, '{{file::')) {
            foreach ($this->insertTagUuids($value) as $uuid) {
                $out[$uuid] = true;
            }
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1) {
            $out[strtolower($value)] = true;

            return;
        }

        // Serialized blob (multiSRC, custom item lists) → recurse into it.
        $deserialized = StringUtil::deserialize($value);
        if (\is_array($deserialized)) {
            $this->collectUuids($deserialized, $out);
        }
    }

    private function toUuid(mixed $bin): ?string
    {
        if (!\is_string($bin) || \strlen($bin) !== 16) {
            return null;
        }

        try {
            return strtolower(StringUtil::binToUuid($bin));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function expandMulti(mixed $blob): array
    {
        if (!\is_string($blob) || $blob === '') {
            return [];
        }

        $items = StringUtil::deserialize($blob, true);
        if (!\is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!\is_string($item)) {
                continue;
            }

            // Contao stores binary UUIDs; tolerate string UUIDs defensively.
            if (\strlen($item) === 16) {
                $uuid = $this->toUuid($item);
                if ($uuid !== null) {
                    $out[] = $uuid;
                }
            } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $item) === 1) {
                $out[] = strtolower($item);
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function insertTagUuids(string $html): array
    {
        if (preg_match_all('/\{\{file::([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $html, $m) === false) {
            return [];
        }

        return array_map('strtolower', $m[1]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = strtolower($table);
        if (!isset($this->columnCache[$key])) {
            if (!$this->tableExists($table)) {
                return false;
            }

            $columns = array_keys($this->schemaManager()->listTableColumns($table));
            $this->columnCache[$key] = array_map('strtolower', $columns);
        }

        return \in_array(strtolower($column), $this->columnCache[$key], true);
    }

    private function tableExists(string $table): bool
    {
        if ($this->tableNames === null) {
            $this->tableNames = array_map('strtolower', $this->schemaManager()->listTableNames());
        }

        return \in_array(strtolower($table), $this->tableNames, true);
    }

    private function schemaManager(): object
    {
        if (method_exists($this->connection, 'createSchemaManager')) {
            return $this->connection->createSchemaManager();
        }

        /** @phpstan-ignore-next-line DBAL 2 fallback for Contao 4.13. */
        return $this->connection->getSchemaManager();
    }
}
