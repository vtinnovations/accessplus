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

/**
 * One-time rename of the legacy product short-code from "tca11y" to "accessplus":
 *   - DB tables tl_tca11y_* → tl_accessplus_* (data preserved via RENAME TABLE)
 *   - state directory var/tca11y → var/accessplus (keeps the license + the
 *     encryption key + stored API secrets)
 *
 * Idempotent: each step only runs when the old name still exists and the new one
 * does not, so re-running contao:migrate is safe. Runs BEFORE the schema diff, so
 * Contao sees the already-renamed tables and does not create empty new ones.
 */
final class RenamePrefixMigration extends AbstractMigration
{
    private const TABLES = [
        'finding', 'run', 'audit', 'altsuggestion', 'track', 'simplification', 'feedback',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
    }

    public function getName(): string
    {
        return 'VT Innovations a11y: rename tl_tca11y_* → tl_accessplus_* and var/tca11y → var/accessplus';
    }

    public function shouldRun(): bool
    {
        foreach (self::TABLES as $t) {
            if ($this->tableExists('tl_tca11y_' . $t) && !$this->tableExists('tl_accessplus_' . $t)) {
                return true;
            }
        }

        return is_dir($this->projectDir . '/var/tca11y') && !is_dir($this->projectDir . '/var/accessplus');
    }

    public function run(): MigrationResult
    {
        $done = [];

        foreach (self::TABLES as $t) {
            $old = 'tl_tca11y_' . $t;
            $new = 'tl_accessplus_' . $t;
            if ($this->tableExists($old) && !$this->tableExists($new)) {
                // Table names are a fixed allow-list constant — no user input.
                $this->connection->executeStatement('RENAME TABLE ' . $old . ' TO ' . $new);
                $done[] = $old . ' → ' . $new;
            }
        }

        $oldDir = $this->projectDir . '/var/tca11y';
        $newDir = $this->projectDir . '/var/accessplus';
        if (is_dir($oldDir) && !is_dir($newDir)) {
            if (@rename($oldDir, $newDir)) {
                $done[] = 'var/tca11y → var/accessplus';
            }
        }

        return $this->createResult(true, $done === [] ? 'Nothing to rename.' : 'Renamed: ' . implode(', ', $done));
    }

    private function tableExists(string $table): bool
    {
        // SHOW TABLES LIKE is MySQL/MariaDB-native (Contao's only supported DB) and
        // avoids the DBAL 2/3 schema-manager API split.
        return (bool) $this->connection->fetchOne('SHOW TABLES LIKE ?', [$table]);
    }
}
