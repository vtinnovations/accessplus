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

namespace VTInnovations\AccessPlus\Subtitle;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Collects UUIDs of media files actually embedded as a player on the site
 * (tl_content / tl_module playerSRC — a serialized UUID list). Used only to mark
 * which media files are visible to visitors, so the subtitle screen can prompt
 * for the ones that matter first. Identifiers in SQL are fixed constants
 * (the project guidelines §3.3).
 */
final class MediaUsageCollector
{
    /** Tables/columns holding a serialized player UUID list. */
    private const PLAYER_SRC = [
        ['tl_content', 'playerSRC'],
        ['tl_module', 'playerSRC'],
    ];

    /** @var array<string, true>|null */
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
     * @return array<string, true> Map of used media UUID (string form) => true.
     */
    public function usedUuids(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $used = [];

        foreach (self::PLAYER_SRC as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }

            $rows = $this->connection->fetchFirstColumn(
                'SELECT ' . $column . ' FROM ' . $table
                . ' WHERE ' . $column . " IS NOT NULL AND " . $column . " != ''",
            );
            foreach ($rows as $blob) {
                foreach ($this->expand($blob) as $uuid) {
                    $used[$uuid] = true;
                }
            }
        }

        return $this->cache = $used;
    }

    /**
     * @return list<string>
     */
    private function expand(mixed $blob): array
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
            if (\is_string($item) && \strlen($item) === 16) {
                try {
                    $out[] = strtolower(StringUtil::binToUuid($item));
                } catch (\Throwable) {
                    // skip malformed
                }
            }
        }

        return $out;
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
