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

use Contao\File;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\Fix\AuditLogger;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Model\TrackModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Orchestrates subtitle generation: list candidate media, transcribe to a VTT
 * DRAFT (never auto-published), let the admin edit, then on approval write the
 * .vtt into files/ and register it (one fix per file+language).
 *
 * Honesty (Bauplan): Whisper output is a draft. It is stored with status pending
 * and only becomes a real file after explicit human review + approval. The
 * source media is never modified.
 */
final class SubtitleService
{
    private const AV_EXTENSIONS = ['mp4', 'm4a', 'mp3', 'webm', 'ogg', 'oga', 'wav', 'mpeg', 'mpga', 'mov'];

    public function __construct(
        private readonly Connection $connection,
        private readonly AudioTranscriber $transcriber,
        private readonly MediaUsageCollector $usage,
        private readonly AuditLogger $auditLogger,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<SubtitleCandidate>
     */
    public function listCandidates(): array
    {
        if (!$this->tableExists('tl_files')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count(self::AV_EXTENSIONS), '?'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT uuid, path FROM tl_files
             WHERE type = \'file\' AND extension IN (' . $placeholders . ')
             ORDER BY path ASC',
            array_values(self::AV_EXTENSIONS),
        );

        $used = $this->usage->usedUuids();
        $out = [];

        foreach ($rows as $row) {
            $uuid = $this->uuidString($row['uuid'] ?? null);
            if ($uuid === null) {
                continue;
            }

            $path = (string) ($row['path'] ?? '');
            $abs = $this->projectDir() . '/' . $path;
            $size = is_file($abs) ? (int) (filesize($abs) ?: 0) : 0;

            $out[] = new SubtitleCandidate(
                uuid: $uuid,
                path: $path,
                name: basename($path),
                sizeBytes: $size,
                used: isset($used[$uuid]),
                track: TrackModel::findOneBy(['sourceUuid=?'], [$uuid], ['order' => 'tstamp DESC']),
            );
        }

        return $out;
    }

    /**
     * Generate (or regenerate) a VTT draft for one media file + language.
     *
     * @throws AiException
     * @throws MediaLoadException
     */
    public function generate(string $uuid, string $lang): TrackModel
    {
        $lang = $this->normaliseLang($lang);

        $path = $this->pathForUuid($uuid);
        if ($path === null) {
            throw new MediaLoadException(Text::get('subtitle.media_not_found_error'));
        }

        $result = $this->transcriber->transcribe($path, $lang);

        $fingerprint = $this->fingerprint($uuid, $lang);
        $model = TrackModel::findOneByFingerprint($fingerprint) ?? new TrackModel();
        if ($model->id === null) {
            $model->createdAt = time();
        }

        $model->tstamp = time();
        $model->sourceUuid = $uuid;
        $model->sourcePath = $path;
        $model->lang = $lang;
        $model->vtt = $result->vtt;
        $model->vttUuid = '';
        $model->vttPath = '';
        $model->status = 'pending';
        $model->provider = $result->provider;
        $model->model = $result->model;
        $model->durationMs = $result->durationMs;
        $model->fingerprint = $fingerprint;
        $model->save();

        return $model;
    }

    /**
     * Persist an edited VTT draft without changing status (human review step).
     */
    public function saveDraft(int $trackId, string $vtt): void
    {
        $track = TrackModel::findById($trackId);
        if ($track === null) {
            return;
        }

        $track->vtt = $this->sanitiseVtt($vtt);
        $track->tstamp = time();
        $track->save();
    }

    /**
     * Approve a draft: write the reviewed VTT into files/ next to the source and
     * register it. Returns the written relative path, or null on failure.
     */
    public function approve(int $trackId): ?string
    {
        $track = TrackModel::findById($trackId);
        if ($track === null || (string) $track->vtt === '') {
            return null;
        }

        $vtt = $this->sanitiseVtt((string) $track->vtt);
        $relPath = $this->vttPathFor((string) $track->sourcePath, (string) $track->lang);

        try {
            $file = new File($relPath);
            $file->truncate();
            $file->write($vtt);
            $file->close();
        } catch (\Throwable $e) {
            $this->logger->error('a11y subtitle: writing VTT failed', ['reason' => $e->getMessage()]);

            return null;
        }

        $uuid = '';
        $fileModel = $file->getModel();
        if ($fileModel !== null && $fileModel->uuid !== null) {
            $uuid = StringUtil::binToUuid($fileModel->uuid);
        }

        $this->auditLogger->record(
            'subtitle_apply',
            'tl_files',
            (string) $track->sourceUuid,
            0,
            'subtitle',
            (string) $track->lang,
            null,
            true,
            $relPath,
        );

        $track->vttUuid = $uuid;
        $track->vttPath = $relPath;
        $track->status = 'applied';
        $track->tstamp = time();
        $track->save();

        return $relPath;
    }

    public function reject(int $trackId): void
    {
        $track = TrackModel::findById($trackId);
        if ($track === null) {
            return;
        }

        $track->status = 'rejected';
        $track->tstamp = time();
        $track->save();
    }

    private function pathForUuid(string $uuid): ?string
    {
        $bin = StringUtil::uuidToBin($uuid);
        $path = $this->connection->fetchOne('SELECT path FROM tl_files WHERE uuid = ?', [$bin]);

        return \is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * files/video/clip.mp4 + de  ->  files/video/clip.de.vtt
     */
    private function vttPathFor(string $sourcePath, string $lang): string
    {
        $dir = \dirname($sourcePath);
        $base = pathinfo($sourcePath, PATHINFO_FILENAME);
        $name = $base . '.' . $lang . '.vtt';

        return ($dir === '.' || $dir === '') ? $name : $dir . '/' . $name;
    }

    /**
     * Defensive: keep only a plausible WebVTT body, plaintext, no script.
     */
    private function sanitiseVtt(string $vtt): string
    {
        $vtt = trim(str_replace(["\r\n", "\r"], "\n", $vtt));
        $vtt = preg_replace('/<\s*script/i', '&lt;script', $vtt) ?? $vtt;

        if ($vtt !== '' && !str_starts_with($vtt, 'WEBVTT')) {
            $vtt = "WEBVTT\n\n" . $vtt;
        }

        return $vtt;
    }

    private function uuidString(mixed $bin): ?string
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

    private function fingerprint(string $uuid, string $lang): string
    {
        return sha1($uuid . '|' . $lang);
    }

    private function normaliseLang(string $lang): string
    {
        $lang = strtolower(trim($lang));

        return $lang !== '' ? $lang : $this->primaryLang();
    }

    private function primaryLang(): string
    {
        $languages = $this->runtimeConfig->get('languages', ['de']);
        if (\is_array($languages) && isset($languages[0]) && \is_string($languages[0]) && $languages[0] !== '') {
            return strtolower(trim($languages[0]));
        }

        return 'de';
    }

    private function projectDir(): string
    {
        return $this->projectDir;
    }

    private function tableExists(string $table): bool
    {
        $names = array_map(
            'strtolower',
            method_exists($this->connection, 'createSchemaManager')
                ? $this->connection->createSchemaManager()->listTableNames()
                : $this->connection->getSchemaManager()->listTableNames(), // @phpstan-ignore-line
        );

        return \in_array(strtolower($table), $names, true);
    }
}
