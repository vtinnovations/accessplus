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

namespace VTInnovations\AccessPlus\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Modell 2 (per-domain scoping) schema + data migration:
 *   1. Ensure the `rootId` column exists on tl_accessplus_finding and
 *      tl_accessplus_run (ADD COLUMN if missing). Done with a plain ALTER so it
 *      also lands on installs whose Contao schema-diff step is blocked by an
 *      unrelated DBAL version mismatch.
 *   2. Back-fill rootId on existing findings from their (ptable, pid) record or,
 *      for frontend findings, from the sampleUrl host — one time, guarded by a
 *      RuntimeConfig flag so contao:migrate never shows it as perpetually pending.
 *
 * Runs BEFORE the schema diff. Idempotent.
 */
final class AddRootIdColumnsMigration extends AbstractMigration
{
    private const FLAG = 'rootid_backfilled';

    public function __construct(
        private readonly Connection $connection,
        private readonly RootScope $rootScope,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    public function getName(): string
    {
        return 'VT Innovations AccessPlus: add rootId to findings/runs and back-fill from page tree';
    }

    public function shouldRun(): bool
    {
        if (!$this->tableExists('tl_accessplus_finding')) {
            return false;
        }

        if (!$this->columnExists('tl_accessplus_finding', 'rootId')
            || !$this->columnExists('tl_accessplus_run', 'rootId')) {
            return true;
        }

        return $this->runtimeConfig->get(self::FLAG, false) !== true;
    }

    public function run(): MigrationResult
    {
        $done = [];

        if ($this->tableExists('tl_accessplus_finding') && !$this->columnExists('tl_accessplus_finding', 'rootId')) {
            $this->connection->executeStatement(
                'ALTER TABLE tl_accessplus_finding ADD COLUMN rootId int(10) unsigned NOT NULL DEFAULT 0 AFTER pid'
            );
            $done[] = 'tl_accessplus_finding.rootId';
        }

        if ($this->tableExists('tl_accessplus_run') && !$this->columnExists('tl_accessplus_run', 'rootId')) {
            $this->connection->executeStatement(
                'ALTER TABLE tl_accessplus_run ADD COLUMN rootId int(10) unsigned NOT NULL DEFAULT 0 AFTER scope'
            );
            $done[] = 'tl_accessplus_run.rootId';
        }

        $filled = $this->backfill();
        if ($filled > 0) {
            $done[] = $filled . ' Befunde zugeordnet';
        }

        $this->runtimeConfig->set(self::FLAG, true);

        return $this->createResult(true, $done === [] ? 'Nothing to do.' : 'Done: ' . implode(', ', $done));
    }

    /**
     * Assign rootId to every finding still at 0 whose record resolves to a root.
     * Returns the number of rows updated.
     */
    private function backfill(): int
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, sourceType, ptable, pid, sampleUrl FROM tl_accessplus_finding WHERE rootId = 0"
            );
        } catch (\Throwable) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            $rootId = $this->resolveRootId(
                (string) ($row['sourceType'] ?? ''),
                (string) ($row['ptable'] ?? ''),
                (int) ($row['pid'] ?? 0),
                (string) ($row['sampleUrl'] ?? ''),
            );

            if ($rootId > 0) {
                $this->connection->executeStatement(
                    'UPDATE tl_accessplus_finding SET rootId = ? WHERE id = ?',
                    [$rootId, (int) $row['id']]
                );
                ++$count;
            }
        }

        return $count;
    }

    private function resolveRootId(string $sourceType, string $ptable, int $pid, string $sampleUrl): int
    {
        if ($sourceType === 'frontend' && $sampleUrl !== '') {
            $host = (string) parse_url($sampleUrl, PHP_URL_HOST);

            return $this->rootScope->rootIdForHost($host);
        }

        return $this->rootScope->rootIdForRecord($ptable, $pid);
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne('SHOW TABLES LIKE ?', [$table]);
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return (bool) $this->connection->fetchOne("SHOW COLUMNS FROM `$table` LIKE ?", [$column]);
        } catch (\Throwable) {
            return false;
        }
    }
}
