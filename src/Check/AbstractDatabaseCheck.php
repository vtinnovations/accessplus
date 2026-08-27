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

namespace VTInnovations\AccessPlus\Check;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Base for database-backed checks: a bound Connection, the active-language list,
 * and dual-version-safe schema introspection (Contao 4.13 ships Doctrine DBAL 2,
 * Contao 5 ships DBAL 3/4 — the schema-manager accessor differs).
 *
 * All SQL in subclasses uses parameter binding only (the project guidelines §3.3).
 */
abstract class AbstractDatabaseCheck implements CheckInterface
{
    /** @var list<string>|null Cached lowercase table names. */
    private ?array $tableNames = null;

    /** @var array<string, list<string>> Cached lowercase column names per table. */
    private array $columnCache = [];

    public function __construct(
        protected readonly Connection $connection,
        protected readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function getSourceType(): SourceType
    {
        return SourceType::Database;
    }

    /**
     * @return list<string>
     */
    protected function activeLanguages(): array
    {
        $languages = $this->runtimeConfig->get('languages', ['de']);
        if (!\is_array($languages) || $languages === []) {
            return ['de'];
        }

        $out = [];
        foreach ($languages as $lang) {
            $lang = strtolower(trim((string) $lang));
            if ($lang !== '') {
                $out[$lang] = $lang;
            }
        }

        return array_values($out) ?: ['de'];
    }

    protected function tableExists(string $table): bool
    {
        if ($this->tableNames === null) {
            $names = $this->schemaManager()->listTableNames();
            $this->tableNames = array_map('strtolower', $names);
        }

        return \in_array(strtolower($table), $this->tableNames, true);
    }

    protected function columnExists(string $table, string $column): bool
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

    /**
     * Resolve the alt text stored in a serialized tl_files.meta blob for one
     * language. Returns null when there is no entry for that language at all,
     * and '' when an (empty) alt is explicitly present.
     */
    protected function metaAlt(?string $serializedMeta, string $lang): ?string
    {
        if ($serializedMeta === null || $serializedMeta === '') {
            return null;
        }

        $meta = StringUtil::deserialize($serializedMeta, true);
        if (!\is_array($meta) || !isset($meta[$lang]) || !\is_array($meta[$lang])) {
            return null;
        }

        return \array_key_exists('alt', $meta[$lang]) ? (string) $meta[$lang]['alt'] : null;
    }

    /**
     * Build a human "where is this" pointer from an optionally page/article-
     * joined row. Missing joins are simply omitted.
     *
     * @param array<string, mixed> $row Expected keys: pageTitle, articleTitle (both optional).
     */
    protected function locationLabel(array $row, string $kind, int $id, string $extra = ''): string
    {
        $parts = [];
        if (!empty($row['pageTitle'])) {
            $parts[] = 'Seite "' . (string) $row['pageTitle'] . '"';
        }
        if (!empty($row['articleTitle'])) {
            $parts[] = 'Artikel "' . (string) $row['articleTitle'] . '"';
        }
        $parts[] = $kind . ' #' . $id . ($extra !== '' ? ' (' . $extra . ')' : '');

        return implode(' › ', $parts);
    }

    /**
     * Dual-version Doctrine DBAL schema-manager accessor.
     */
    private function schemaManager(): object
    {
        if (method_exists($this->connection, 'createSchemaManager')) {
            return $this->connection->createSchemaManager();
        }

        /** @phpstan-ignore-next-line DBAL 2 fallback for Contao 4.13. */
        return $this->connection->getSchemaManager();
    }
}
