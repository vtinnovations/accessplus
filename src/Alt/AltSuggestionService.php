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
use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\Fix\AuditLogger;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Media\UsedImageCollector;
use VTInnovations\AccessPlus\Model\AltSuggestionModel;
use VTInnovations\AccessPlus\Model\AuditModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Orchestrates alt-text proposals: batch-generate for images whose meta has no
 * alt yet, and apply/reject individual proposals.
 *
 * Guarantees (the project guidelines §3.2/§5):
 *   - Generation NEVER writes tl_files.meta — it only stores proposals
 *     (status pending). Publishing is a separate, explicit approval.
 *   - The egress kill-switch is checked up front; with it on, no call is made.
 *   - Only images with a genuinely missing alt are targeted; existing alts
 *     (manual or already applied) are left untouched.
 *   - Idempotent: a pending/applied proposal for the same file+language is not
 *     regenerated, so re-runs don't re-spend tokens.
 */
final class AltSuggestionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AltTextGenerator $generator,
        private readonly ImageLoader $imageLoader,
        private readonly MetaWriter $metaWriter,
        private readonly AuditLogger $auditLogger,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly UsedImageCollector $usedImages,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generateForMissing(int $limit = 25): AltSummary
    {
        if ($this->runtimeConfig->externalCallsBlocked()) {
            return new AltSummary(true, 0, 0, 0, Text::get('alt.generation_blocked_message'));
        }

        // Usage-centric: every managed raster image that is actually embedded
        // somewhere (galleries/multiSRC, sliders, modules, insert tags), via
        // UsedImageCollector — not orphaned Files-manager uploads. The canonical
        // alt lives in tl_files.meta, so one proposal benefits every usage. SVG
        // is excluded (vision needs raster).
        $used = $this->usedImages->usedUuids();
        if ($used === []) {
            return new AltSummary(false, 0, 0, 0, Text::get('alt.no_used_images_message'));
        }

        $files = $this->connection->fetchAllAssociative(
            "SELECT f.uuid AS uuid, f.path AS path, f.meta AS meta
             FROM tl_files f
             WHERE f.type = 'file'
               AND f.extension IN ('jpg','jpeg','png','gif','webp')",
        );

        $languages = $this->activeLanguages();
        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            if ($generated >= $limit) {
                break;
            }

            $uuid = strtolower(StringUtil::binToUuid(\is_string($file['uuid']) ? $file['uuid'] : (string) $file['uuid']));
            if (!isset($used[$uuid])) {
                continue;
            }
            $path = (string) ($file['path'] ?? '');
            $meta = \is_string($file['meta'] ?? null) ? $file['meta'] : null;

            $missingLangs = [];
            foreach ($languages as $lang) {
                if ($this->metaAlt($meta, $lang) !== null) {
                    continue; // alt present (or deliberately empty) → leave it.
                }

                // Alt is missing. Decide based on any existing proposal:
                $existing = AltSuggestionModel::findOneByFingerprint(sha1($uuid . '|' . $lang));
                if ($existing === null) {
                    $missingLangs[] = $lang; // never proposed → generate (API call).
                    continue;
                }
                if ($existing->status === 'applied') {
                    // We applied an alt earlier but it is gone again (editor removed
                    // it). Re-surface the existing suggestion for review — no new API
                    // cost — instead of silently ignoring the now-missing alt.
                    $existing->status = 'pending';
                    $existing->tstamp = time();
                    $existing->save();
                    ++$skipped;
                }
                // pending → already queued; rejected → respect the human decision.
            }

            if ($missingLangs === []) {
                continue;
            }

            try {
                $image = $this->imageLoader->load($path);
            } catch (ImageLoadException $e) {
                ++$errors;
                $this->logger->warning('a11y alt: image load failed', ['path' => $path, 'reason' => $e->getMessage()]);
                continue;
            }

            foreach ($missingLangs as $lang) {
                if ($generated >= $limit) {
                    break;
                }

                try {
                    $proposal = $this->generator->generate($image, $lang, $this->buildContext($path, $meta, $lang));
                } catch (AiException $e) {
                    ++$errors;
                    $this->logger->warning('a11y alt: generation failed', ['kind' => $e->kind->value]);

                    // Auth/egress problems won't fix themselves mid-batch — stop.
                    if (!$e->isRetryable()) {
                        return new AltSummary(false, $generated, $skipped, $errors, Text::get('common.aborted_prefix', ['message' => $e->getMessage()]));
                    }
                    continue;
                }

                $this->persist($uuid, $path, $lang, $proposal);
                ++$generated;
            }
        }

        return new AltSummary(
            false,
            $generated,
            $skipped,
            $errors,
            Text::get('alt.generated_summary', ['generated' => $generated, 'skipped' => $skipped, 'errors' => $errors]),
        );
    }

    public function approve(int $suggestionId, ?string $altOverride = null): MetaWriteResult
    {
        $suggestion = AltSuggestionModel::findById($suggestionId);
        if ($suggestion === null) {
            return MetaWriteResult::NotFound;
        }

        if ($altOverride !== null) {
            // Editor adjusted the text before approving — empty = decorative.
            $alt = trim($altOverride);
            $suggestion->suggestion = $alt;
            $suggestion->decorative = $alt === '' ? '1' : '';
        } else {
            $alt = $suggestion->decorative === '1' ? '' : trim((string) $suggestion->suggestion);
        }
        $uuid = (string) $suggestion->fileUuid;
        $lang = (string) $suggestion->lang;

        // Capture the prior state BEFORE writing, so the change is undoable.
        $before = $this->metaWriter->readAlt($uuid, $lang);

        $result = $this->metaWriter->writeAlt($uuid, $lang, $alt);

        if ($result === MetaWriteResult::Written) {
            $this->auditLogger->record(
                'alt_apply',
                'tl_files',
                $uuid,
                0,
                'alt',
                $lang,
                $before['value'],
                $before['absent'],
                $alt,
            );
            $suggestion->status = 'applied';
        } else {
            $suggestion->status = 'rejected';
        }

        $suggestion->tstamp = time();
        $suggestion->save();

        return $result;
    }

    public function reject(int $suggestionId): void
    {
        $suggestion = AltSuggestionModel::findById($suggestionId);
        if ($suggestion === null) {
            return;
        }

        $suggestion->status = 'rejected';
        $suggestion->tstamp = time();
        $suggestion->save();
    }

    /**
     * Reverts an applied alt change (no-clobber: only if unchanged since). The
     * related suggestion goes back to pending so it can be re-reviewed.
     */
    public function undo(int $auditId): bool
    {
        $entry = AuditModel::findById($auditId);
        if ($entry === null || $entry->action !== 'alt_apply' || $entry->undone === '1') {
            return false;
        }

        $ok = $this->metaWriter->restoreAlt(
            (string) $entry->targetUuid,
            (string) $entry->lang,
            (string) $entry->afterValue,
            $entry->beforeValue,
            $entry->beforeAbsent === '1',
        );

        if (!$ok) {
            return false;
        }

        $entry->undone = '1';
        $entry->tstamp = time();
        $entry->save();

        $suggestion = AltSuggestionModel::findOneByFingerprint(sha1((string) $entry->targetUuid . '|' . (string) $entry->lang));
        if ($suggestion !== null) {
            $suggestion->status = 'pending';
            $suggestion->tstamp = time();
            $suggestion->save();
        }

        return true;
    }

    private function persist(string $uuid, string $path, string $lang, AltProposal $proposal): void
    {
        $fingerprint = sha1($uuid . '|' . $lang);

        $model = AltSuggestionModel::findOneByFingerprint($fingerprint) ?? new AltSuggestionModel();
        if ($model->id === null) {
            $model->createdAt = time();
        }

        $model->tstamp = time();
        $model->fileUuid = $uuid;
        $model->filePath = $path;
        $model->lang = $lang;
        $model->suggestion = $proposal->alt;
        $model->decorative = $proposal->decorative ? '1' : '';
        $model->status = 'pending';
        $model->provider = (string) $this->runtimeConfig->get('ai_provider', '');
        $model->model = (string) $this->runtimeConfig->get('ai_model', '');
        $model->fingerprint = $fingerprint;
        $model->save();
    }

    private function hasLiveProposal(string $uuid, string $lang): bool
    {
        $existing = AltSuggestionModel::findOneByFingerprint(sha1($uuid . '|' . $lang));

        return $existing !== null && \in_array($existing->status, ['pending', 'applied'], true);
    }

    private function buildContext(string $path, ?string $meta, string $lang): string
    {
        $parts = ['Dateiname: ' . basename($path)];

        $deserialized = $meta !== null ? StringUtil::deserialize($meta, true) : [];
        if (\is_array($deserialized) && \is_array($deserialized[$lang] ?? null)) {
            foreach (['title', 'caption'] as $key) {
                $value = trim((string) ($deserialized[$lang][$key] ?? ''));
                if ($value !== '') {
                    $parts[] = ucfirst($key) . ': ' . $value;
                }
            }
        }

        return mb_substr(implode(' · ', $parts), 0, 240);
    }

    private function metaAlt(?string $serializedMeta, string $lang): ?string
    {
        if ($serializedMeta === null || $serializedMeta === '') {
            return null;
        }

        $meta = StringUtil::deserialize($serializedMeta, true);
        if (!\is_array($meta) || !\is_array($meta[$lang] ?? null)) {
            return null;
        }

        return \array_key_exists('alt', $meta[$lang]) ? (string) $meta[$lang]['alt'] : null;
    }

    /**
     * @return list<string>
     */
    private function activeLanguages(): array
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
}
