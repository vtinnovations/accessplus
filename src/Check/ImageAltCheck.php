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

use Doctrine\DBAL\Connection;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Media\UsedImageCollector;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Flags image FILES that are USED on the site and whose tl_files.meta has no alt
 * text for one or more active languages (WCAG 1.1.1).
 *
 * USAGE-CENTRIC: a file is only flagged when it is actually embedded somewhere
 * (singleSRC, multiSRC galleries/sliders/modules, or a {{file::UUID}} insert tag
 * in rich text) — see UsedImageCollector. This catches gallery/slider/module
 * images the old content-only scan missed, while ignoring orphaned files in the
 * Files manager that aren't shown anywhere. The canonical alt lives in
 * tl_files.meta, so one fix covers every place the file is used.
 *
 * Severity is Moderate, not Critical: an empty alt is *correct* for a purely
 * decorative image; the AI/human track decides (Phase 3). Only a genuinely
 * ABSENT alt is flagged — an explicitly empty one counts as a decision made.
 */
final class ImageAltCheck extends AbstractDatabaseCheck
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        Connection $connection,
        RuntimeConfig $runtimeConfig,
        private readonly UsedImageCollector $usedImages,
    ) {
        parent::__construct($connection, $runtimeConfig);
    }

    public function getId(): string
    {
        return 'image_alt_missing';
    }

    public function getLabel(): string
    {
        return Text::get('check.image_alt_missing');
    }

    public function scan(): iterable
    {
        if (!$this->tableExists('tl_files')) {
            return;
        }

        $used = $this->usedImages->usedUuids();
        if ($used === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, \count(self::EXTENSIONS), '?'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id AS fileId, uuid, path, meta FROM tl_files
             WHERE extension IN (' . $placeholders . ")
               AND type = 'file'",
            array_values(self::EXTENSIONS),
        );

        $languages = $this->activeLanguages();

        foreach ($rows as $row) {
            $uuid = $this->uuidString($row['uuid'] ?? null);
            if ($uuid === null || !isset($used[$uuid])) {
                continue;
            }

            $missing = [];
            foreach ($languages as $lang) {
                if ($this->metaAlt(\is_string($row['meta'] ?? null) ? $row['meta'] : null, $lang) === null) {
                    $missing[] = $lang;
                }
            }

            if ($missing === []) {
                continue;
            }

            $fileId = (int) $row['fileId'];
            $path = (string) ($row['path'] ?? '');
            $name = basename($path);

            yield new Finding(
                checkId: $this->getId(),
                wcagCriteria: ['1.1.1'],
                severity: Severity::Moderate,
                sourceType: SourceType::Database,
                ptable: 'tl_files',
                pid: $fileId,
                field: 'alt',
                elementLabel: 'Datei "' . $name . '" (' . $path . ')',
                message: sprintf('Bilddatei "%s" ohne Alt-Text (%s).', $name, implode(', ', $missing)),
                suggestion: 'Alt-Text je Sprache in den Datei-Eigenschaften ergänzen — oder das Bild als dekorativ markieren (bewusst leeres alt). KI-Vorschlag im Modul „KI-Alt-Texte".',
            );
        }
    }

    private function uuidString(mixed $bin): ?string
    {
        if (!\is_string($bin) || \strlen($bin) !== 16) {
            return null;
        }

        try {
            return strtolower(\Contao\StringUtil::binToUuid($bin));
        } catch (\Throwable) {
            return null;
        }
    }
}
