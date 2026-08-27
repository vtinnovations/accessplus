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

namespace VTInnovations\AccessPlus\Pdf;

use Doctrine\DBAL\Connection;
use VTInnovations\AccessPlus\Check\Finding;
use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\Check\Severity;
use VTInnovations\AccessPlus\Check\SourceType;
use VTInnovations\AccessPlus\Model\FindingModel;

/**
 * Analyses the linked PDFs and records concrete, WCAG-mapped findings (missing
 * title / language / tags). Runs on demand (module button or CLI), not on every
 * monitor tick, because opening many PDFs is comparatively heavy.
 *
 * Owns its own findings (checkId pdf_*) and reconciles them idempotently — the
 * DB linter never touches them (LintRunner scopes by its own checkIds).
 */
final class PdfScanService
{
    private const CHECK_IDS = ['pdf_no_title', 'pdf_no_lang', 'pdf_not_tagged'];

    /** Includes the legacy 'pdf_reference' id so old findings auto-resolve. */
    private const RECONCILE_IDS = ['pdf_no_title', 'pdf_no_lang', 'pdf_not_tagged', 'pdf_reference'];

    public function __construct(
        private readonly Connection $connection,
        private readonly PdfAnalyzer $analyzer,
    ) {
    }

    public function scan(): PdfScanSummary
    {
        $files = $this->linkedPdfs();

        /** @var array<string, Finding> $current */
        $current = [];
        $checked = 0;
        $unknown = 0;
        $unreadable = 0;

        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            $fileId = (int) ($file['fid'] ?? 0);
            $report = $this->analyzer->analyze($path);

            if (!$report->ok) {
                ++$unreadable;
                continue;
            }

            ++$checked;
            $name = basename($path);

            if (!$report->hasTitle()) {
                $f = $this->finding('pdf_no_title', ['2.4.2'], Severity::Moderate, $fileId, $name,
                    'PDF ohne Dokumenttitel.',
                    'Im Erstellungsprogramm einen Dokumenttitel setzen und als getaggtes PDF (PDF/UA) neu exportieren.');
                $current[$f->fingerprint()] = $f;
            }

            if ($report->langState === 'missing') {
                $f = $this->finding('pdf_no_lang', ['3.1.1'], Severity::Moderate, $fileId, $name,
                    'PDF ohne Dokumentsprache.',
                    'Sprache im PDF setzen (Dateieigenschaften → Erweitert → Sprache) bzw. beim Export angeben.');
                $current[$f->fingerprint()] = $f;
            } elseif ($report->langState === 'unknown') {
                ++$unknown;
            }

            if ($report->tagState === 'untagged') {
                $f = $this->finding('pdf_not_tagged', ['1.3.1'], Severity::Serious, $fileId, $name,
                    'PDF ist nicht getaggt (keine Strukturinformation).',
                    'Getaggtes PDF/UA aus der Quelle neu exportieren; nachträgliches Taggen via Acrobat o. Ä.');
                $current[$f->fingerprint()] = $f;
            } elseif ($report->tagState === 'unknown') {
                ++$unknown;
            }
        }

        $issues = $this->reconcile($current);

        return new PdfScanSummary($checked, $issues, $unknown, $unreadable);
    }

    /**
     * @param array<string, Finding> $current
     *
     * @return int Open issue count after reconcile.
     */
    private function reconcile(array $current): int
    {
        $existing = $this->existingPdfFindings();
        $byFingerprint = [];
        foreach ($existing as $model) {
            $byFingerprint[(string) $model->fingerprint] = $model;
        }

        $now = time();
        $seen = [];
        $open = 0;

        foreach ($current as $fingerprint => $finding) {
            $seen[$fingerprint] = true;
            $model = $byFingerprint[$fingerprint] ?? null;

            if ($model === null) {
                $model = new FindingModel();
                $model->createdAt = $now;
                $model->status = FindingStatus::Open->value;
            } elseif ($model->status === FindingStatus::Fixed->value) {
                $model->status = FindingStatus::Open->value;
            }

            $model->tstamp = $now;
            $model->checkId = $finding->checkId;
            $model->wcagSc = $finding->wcagString();
            $model->severity = $finding->severity->value;
            $model->sourceType = $finding->sourceType->value;
            $model->ptable = $finding->ptable;
            $model->pid = $finding->pid;
            $model->field = $finding->field;
            $model->elementLabel = $finding->elementLabel;
            $model->message = $finding->message;
            $model->suggestion = $finding->suggestion;
            $model->fingerprint = $fingerprint;
            $model->save();

            if (\in_array($model->status, [FindingStatus::Open->value, FindingStatus::Confirmed->value], true)) {
                ++$open;
            }
        }

        foreach ($existing as $model) {
            if (!isset($seen[(string) $model->fingerprint]) && $model->status !== FindingStatus::Fixed->value) {
                $model->status = FindingStatus::Fixed->value;
                $model->tstamp = $now;
                $model->save();
            }
        }

        return $open;
    }

    /**
     * @param list<string> $wcag
     */
    private function finding(string $checkId, array $wcag, Severity $severity, int $fileId, string $name, string $message, string $suggestion): Finding
    {
        return new Finding(
            checkId: $checkId,
            wcagCriteria: $wcag,
            severity: $severity,
            sourceType: SourceType::Manual,
            ptable: 'tl_files',
            pid: $fileId,
            field: $checkId,
            elementLabel: 'PDF "' . $name . '"',
            message: $message,
            suggestion: $suggestion,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linkedPdfs(): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT DISTINCT f.id AS fid, f.path AS path
                 FROM tl_content c
                 INNER JOIN tl_files f ON f.uuid = c.singleSRC
                 WHERE f.extension = 'pdf' AND c.singleSRC IS NOT NULL AND LENGTH(c.singleSRC) = 16",
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<FindingModel>
     */
    private function existingPdfFindings(): array
    {
        $placeholders = implode(',', array_fill(0, \count(self::RECONCILE_IDS), '?'));
        $collection = FindingModel::findBy(['checkId IN (' . $placeholders . ')'], self::RECONCILE_IDS);
        if ($collection === null) {
            return [];
        }

        $out = [];
        foreach ($collection as $model) {
            $out[] = $model;
        }

        return $out;
    }
}
