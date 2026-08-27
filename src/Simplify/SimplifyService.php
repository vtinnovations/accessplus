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

namespace VTInnovations\AccessPlus\Simplify;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Model\SimplificationModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Orchestrates plain/easy-language drafts: gather a page's text elements,
 * generate a simplified draft per element (status pending, never auto-published),
 * review, approve/reject. Approved drafts are swapped into the frontend in place
 * by SimpleLanguageRenderer; the original tl_content is never modified.
 */
final class SimplifyService
{
    /** Registers we support. */
    public const REGISTERS = ['einfach', 'leicht'];

    public function __construct(
        private readonly Connection $connection,
        private readonly SimpleTextGenerator $generator,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Published regular pages, for the backend selector.
     *
     * @return list<array{id:int,title:string,alias:string,language:string}>
     */
    public function listPages(): array
    {
        if (!$this->tableExists('tl_page')) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title, alias, language FROM tl_page
             WHERE type = 'regular' AND published = '1'
             ORDER BY sorting ASC",
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'title' => (string) ($row['title'] ?? ''),
                'alias' => (string) ($row['alias'] ?? ''),
                'language' => (string) ($row['language'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Text content elements of a page + their current draft for a register/lang.
     *
     * @return list<SimplifyItem>
     */
    public function itemsForPage(int $pageId, string $register, string $lang): array
    {
        $rows = $this->gather($pageId);
        $out = [];

        foreach ($rows as $row) {
            $contentId = (int) $row['id'];
            $snippetKey = (string) $row['snippetKey'];
            $out[] = new SimplifyItem(
                contentId: $contentId,
                snippetKey: $snippetKey,
                type: (string) $row['type'],
                originalHtml: (string) $row['text'],
                draft: SimplificationModel::findOneByFingerprint($this->fingerprint($contentId, $snippetKey, $register, $lang)),
            );
        }

        return $out;
    }

    public function generateForPage(int $pageId, string $register, string $lang, int $limit = 200): SimplifySummary
    {
        if ($this->runtimeConfig->externalCallsBlocked()) {
            return new SimplifySummary(true, 0, 0, 0, Text::get('simple.generation_blocked_message'));
        }

        $register = $this->normaliseRegister($register);
        $lang = $this->normaliseLang($lang);

        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($this->gather($pageId) as $row) {
            if ($generated >= $limit) {
                break;
            }

            $contentId = (int) $row['id'];
            $snippetKey = (string) $row['snippetKey'];
            $source = trim((string) $row['text']);
            if ($source === '') {
                continue;
            }

            $hash = sha1($source);
            $fingerprint = $this->fingerprint($contentId, $snippetKey, $register, $lang);
            $existing = SimplificationModel::findOneByFingerprint($fingerprint);

            // Locked = pinned by the editor: never regenerate, even if the source
            // text changed. Keeps a hand-tuned simplification untouched.
            if ($existing !== null && $existing->locked === '1') {
                ++$skipped;
                continue;
            }

            // Skip if a live draft for the unchanged source already exists.
            if ($existing !== null
                && \in_array($existing->status, ['pending', 'approved'], true)
                && (string) $existing->sourceHash === $hash
            ) {
                ++$skipped;
                continue;
            }

            try {
                $draftHtml = $this->generator->generate($source, $register, $lang);
            } catch (AiException $e) {
                ++$errors;
                $this->logger->warning('a11y simplify: generation failed', ['kind' => $e->kind->value]);

                if (!$e->isRetryable()) {
                    return new SimplifySummary(false, $generated, $skipped, $errors, Text::get('common.aborted_prefix', ['message' => $e->getMessage()]));
                }
                continue;
            }

            if ($draftHtml === '') {
                ++$errors;
                continue;
            }

            $this->persist($existing, $pageId, $contentId, $snippetKey, $register, $lang, $source, $draftHtml, $hash);
            ++$generated;
        }

        return new SimplifySummary(
            false,
            $generated,
            $skipped,
            $errors,
            Text::get('simple.generated_summary', ['generated' => $generated, 'skipped' => $skipped, 'errors' => $errors]),
        );
    }

    public function saveDraft(int $id, string $html): void
    {
        $model = SimplificationModel::findById($id);
        if ($model === null) {
            return;
        }

        $model->draft = $this->sanitise($html);
        $model->tstamp = time();
        $model->save();
    }

    public function approve(int $id): void
    {
        $this->setStatus($id, 'approved');
    }

    /**
     * Pin a draft: mark it approved (so it goes live) AND locked (so future
     * "generate" runs never overwrite it).
     */
    public function lock(int $id): void
    {
        $model = SimplificationModel::findById($id);
        if ($model === null) {
            return;
        }

        $model->locked = '1';
        $model->status = 'approved';
        $model->tstamp = time();
        $model->save();
    }

    public function unlock(int $id): void
    {
        $model = SimplificationModel::findById($id);
        if ($model === null) {
            return;
        }

        $model->locked = '';
        $model->tstamp = time();
        $model->save();
    }

    public function reject(int $id): void
    {
        $this->setStatus($id, 'rejected');
    }

    public function approveAll(int $pageId, string $register, string $lang): int
    {
        $count = 0;
        foreach ($this->itemsForPage($pageId, $register, $lang) as $item) {
            if ($item->draft !== null && $item->draft->status === 'pending') {
                $item->draft->status = 'approved';
                $item->draft->tstamp = time();
                $item->draft->save();
                ++$count;
            }
        }

        return $count;
    }

    private function setStatus(int $id, string $status): void
    {
        $model = SimplificationModel::findById($id);
        if ($model === null) {
            return;
        }

        $model->status = $status;
        $model->tstamp = time();
        $model->save();
    }

    private function persist(?SimplificationModel $existing, int $pageId, int $contentId, string $snippetKey, string $register, string $lang, string $source, string $draft, string $hash): void
    {
        $model = $existing ?? new SimplificationModel();
        if ($model->id === null) {
            $model->createdAt = time();
        }

        $model->tstamp = time();
        $model->pageId = $pageId;
        $model->contentId = $contentId;
        $model->snippetKey = $snippetKey;
        $model->register = $register;
        $model->lang = $lang;
        $model->sourceText = $source;
        $model->draft = $draft;
        $model->sourceHash = $hash;
        $model->status = 'pending';
        $model->provider = (string) $this->runtimeConfig->get('ai_provider', '');
        $model->model = (string) $this->runtimeConfig->get('ai_model', '');
        $model->fingerprint = $this->fingerprint($contentId, $snippetKey, $register, $lang);
        $model->save();
    }

    /**
     * Text snippets of a page's content elements. One row per simplifiable text:
     * core elements (text/headline) yield a single 'main' snippet; RockSolid
     * Custom Elements (rsce_*) yield one per prose string found in rsce_data
     * (icon-box titles/descriptions, teasers, …) keyed by content hash.
     *
     * @return list<array{id:int,type:string,snippetKey:string,text:string}>
     */
    private function gather(int $pageId): array
    {
        if (!$this->tableExists('tl_content')) {
            return [];
        }

        // SELECT * so we can scan EVERY column for prose — theme/extension custom
        // elements (icon boxes, cards) keep their copy in their own columns, not
        // text/headline. pageId <= 0 → whole site.
        if ($pageId > 0 && $this->tableExists('tl_article')) {
            $articleIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_article WHERE pid = ?', [$pageId]);
            if ($articleIds === []) {
                return [];
            }
            $inArticles = implode(',', array_fill(0, \count($articleIds), '?'));
            $rows = $this->connection->fetchAllAssociative(
                "SELECT * FROM tl_content
                 WHERE ptable = 'tl_article' AND pid IN (" . $inArticles . ") AND invisible = ''
                 ORDER BY sorting ASC",
                array_map('intval', $articleIds),
            );
        } else {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT * FROM tl_content WHERE invisible = '' ORDER BY pid ASC, sorting ASC",
            );
        }

        $out = [];
        foreach ($rows as $row) {
            $contentId = (int) $row['id'];
            $type = (string) ($row['type'] ?? '');

            foreach ($this->snippetsForRow($row) as $snippet) {
                $out[] = [
                    'id' => $contentId,
                    'type' => $type,
                    'snippetKey' => $snippet['key'],
                    'text' => $snippet['text'],
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{key:string,text:string}>
     */
    private function snippetsForRow(array $row): array
    {
        // Scan EVERY column for prose: the standard `text` body, the serialized
        // `headline`, rsce_data JSON, and any theme/custom columns (icon-box
        // descriptions, teasers). No early return on `text` — custom elements
        // often store the icon class (or junk) in `text` and the real copy in a
        // different column, so we must look at all of them.
        $found = [];
        foreach ($row as $column => $value) {
            if (\in_array($column, ['id', 'pid', 'sorting', 'tstamp', 'ptable', 'type', 'cssID'], true)) {
                continue;
            }
            $this->collectProse($value, $found);
        }

        $out = [];
        $seen = [];
        foreach ($found as $value) {
            $key = substr(sha1($value), 0, 16);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['key' => $key, 'text' => trim($value)];
        }

        return $out;
    }

    /**
     * Recursively collect prose strings from a value that may be a plain string,
     * a JSON blob (rsce_data) or a Contao-serialized blob (item lists). Keeps only
     * sentence-like strings (length, a space, not URLs/paths/CSS tokens).
     *
     * @param list<string> $out
     */
    private function collectProse(mixed $value, array &$out): void
    {
        if (\is_array($value)) {
            foreach ($value as $v) {
                $this->collectProse($v, $out);
            }

            return;
        }

        if (!\is_string($value) || $value === '') {
            return;
        }

        // Unwrap a nested structure (JSON first, then Contao serialize).
        $trimmed = ltrim($value);
        if (($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '['))) {
            $decoded = json_decode($value, true);
            if (\is_array($decoded)) {
                $this->collectProse($decoded, $out);

                return;
            }
        }
        $unser = StringUtil::deserialize($value);
        if (\is_array($unser)) {
            $this->collectProse($unser, $out);

            return;
        }

        // Decode entities BEFORE filtering — otherwise "&amp;" brings a ';' that
        // the CSS filter below would wrongly treat as a style declaration, which
        // killed every card description containing "&".
        $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (mb_strlen($plain) < 12 || !str_contains($plain, ' ')) {
            return;
        }
        // URLs, paths, insert tags, media file names.
        if (preg_match('#^(https?:|/|\{\{|#?[0-9a-f]{3,8}$|[a-z0-9_./-]+\.(webp|jpe?g|png|svg|gif|mp4|pdf))#i', $plain) === 1) {
            return;
        }
        // CSS / style values from theme custom columns (colours, declarations):
        //   "247, 247, 247,1"  ·  "border: 1px solid #010e1e;"  ·  "12px 0 0"
        if (str_contains($plain, ';') || str_contains($plain, '{')) {
            return;
        }
        if (preg_match('/#[0-9a-fA-F]{3,8}\b/', $plain) === 1) {                 // hex colour
            return;
        }
        if (preg_match('/^\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}/', $plain) === 1) { // rgb(a) triplet
            return;
        }
        if (preg_match('/\b\d+(\.\d+)?(px|em|rem|pt|vh|vw|%)\b/i', $plain) === 1) {  // CSS units
            return;
        }
        // Font-icon class strings: "fa-thermometer-three-quarters fa",
        // "fa fa-life-ring", "ph-fill-person-simple-swim", "bi-...", "icon-...".
        if (preg_match('/(^|\s)(fa[srlbd]?|fa|ph|bi|glyphicon|material-icons|icon)[\s-]/i', $plain) === 1
            && preg_match('/[a-z]{2,}-[a-z]{2,}/i', $plain) === 1
        ) {
            return;
        }
        // Must read like prose: at least two real words (3+ letters each) AND a
        // space separating actual words (not just a hyphenated token).
        if (preg_match_all('/\p{L}{3,}/u', $plain) < 2 || !preg_match('/\p{L}{3,}\s+\p{L}{3,}/u', $plain)) {
            return;
        }

        $out[] = trim($value);
    }

    private function columnExists(string $table, string $column): bool
    {
        $sm = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager(); // @phpstan-ignore-line

        if (!\in_array(strtolower($table), array_map('strtolower', $sm->listTableNames()), true)) {
            return false;
        }

        return \in_array(strtolower($column), array_map('strtolower', array_keys($sm->listTableColumns($table))), true);
    }

    /**
     * Extract the text value from a serialized tl_content.headline ({unit,value}).
     */
    private function headlineValue(string $serialized): string
    {
        if ($serialized === '') {
            return '';
        }

        $data = StringUtil::deserialize($serialized);
        if (\is_array($data)) {
            return trim((string) ($data['value'] ?? ''));
        }

        return trim($serialized);
    }

    private function sanitise(string $html): string
    {
        // Plain text only (no tags) — the simplified copy is swapped into the
        // existing markup as a text node, so it must not carry its own tags.
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function fingerprint(int $contentId, string $snippetKey, string $register, string $lang): string
    {
        return sha1($contentId . '|' . $snippetKey . '|' . $register . '|' . $lang);
    }

    private function normaliseRegister(string $register): string
    {
        $register = strtolower(trim($register));

        return \in_array($register, self::REGISTERS, true) ? $register : 'einfach';
    }

    private function normaliseLang(string $lang): string
    {
        $lang = strtolower(trim($lang));

        return $lang !== '' ? substr($lang, 0, 2) : 'de';
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
