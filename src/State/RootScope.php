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

namespace VTInnovations\AccessPlus\State;

use Doctrine\DBAL\Connection;

/**
 * Resolves a Contao site root (tl_page type='root') for the per-domain (Modell 2)
 * scoping: findings, config overrides, score/report and the accessibility
 * statement all key on a rootId. rootId 0 means "install-wide / shared" (e.g.
 * tl_files findings that belong to no single site tree).
 *
 * Autowired (Connection). Framework-light: plain SQL walks, no PageModel boot,
 * so it works identically on Contao 4.13 and 5.x and inside migrations/CLI.
 */
final class RootScope
{
    private const MAX_TRAIL = 100;

    /** @var array<int, int>|null pageId => rootId trail cache (per request) */
    private ?array $trailCache = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * All site roots, newest sorting first. Empty when the schema has no
     * tl_page yet (fresh install / CLI before setup).
     *
     * @return list<array{id: int, dns: string, language: string, title: string, useSSL: bool}>
     */
    public function roots(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, dns, language, title, useSSL FROM tl_page WHERE type = 'root' ORDER BY sorting"
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'       => (int) $r['id'],
                'dns'      => (string) ($r['dns'] ?? ''),
                'language' => (string) ($r['language'] ?? ''),
                'title'    => (string) ($r['title'] ?? ''),
                'useSSL'   => (bool) ($r['useSSL'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * A single root by id, or null.
     *
     * @return array{id: int, dns: string, language: string, title: string, useSSL: bool}|null
     */
    public function root(int $rootId): ?array
    {
        foreach ($this->roots() as $root) {
            if ($root['id'] === $rootId) {
                return $root;
            }
        }

        return null;
    }

    /**
     * Human label for a root: the domain if set, else the title, plus language.
     *
     * @param array{id: int, dns: string, language: string, title: string, useSSL: bool} $root
     */
    public function label(array $root): string
    {
        $name = $root['dns'] !== '' ? $root['dns'] : ($root['title'] !== '' ? $root['title'] : 'Root #' . $root['id']);

        return $root['language'] !== '' ? $name . ' (' . $root['language'] . ')' : $name;
    }

    /**
     * The rootId of an arbitrary page by walking pid up to the type='root' node.
     * Returns 0 when the page is gone or has no root ancestor.
     */
    public function rootIdForPage(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        if (isset($this->trailCache[$pageId])) {
            return $this->trailCache[$pageId];
        }

        $id = $pageId;
        $seen = [];
        for ($i = 0; $i < self::MAX_TRAIL; ++$i) {
            if ($id <= 0 || isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;

            try {
                $row = $this->connection->fetchAssociative('SELECT id, pid, type FROM tl_page WHERE id = ?', [$id]);
            } catch (\Throwable) {
                break;
            }
            if ($row === false) {
                break;
            }
            if (($row['type'] ?? '') === 'root') {
                return $this->trailCache[$pageId] = (int) $row['id'];
            }
            $id = (int) ($row['pid'] ?? 0);
        }

        return $this->trailCache[$pageId] = 0;
    }

    /**
     * The rootId a linter finding's (ptable, pid) record belongs to:
     *   - tl_page    → the page's own root (a root page returns itself)
     *   - tl_content → the owning article's page → root
     *   - everything else (tl_files, tl_form_field) → 0 (shared / install-wide)
     */
    public function rootIdForRecord(string $ptable, int $pid): int
    {
        return match ($ptable) {
            'tl_page'    => $this->rootIdForPage($pid),
            'tl_content' => $this->rootIdForPage($this->pageOfContent($pid)),
            default      => 0,
        };
    }

    /**
     * Page id owning a tl_content element: content.pid → tl_article.id →
     * article.pid. 0 when it is not article content or the chain is broken.
     */
    public function pageOfContent(int $contentId): int
    {
        if ($contentId <= 0) {
            return 0;
        }

        try {
            $pageId = $this->connection->fetchOne(
                'SELECT a.pid FROM tl_content c INNER JOIN tl_article a ON c.pid = a.id WHERE c.id = ? AND c.ptable = ?',
                [$contentId, 'tl_article']
            );
        } catch (\Throwable) {
            return 0;
        }

        return \is_numeric($pageId) ? (int) $pageId : 0;
    }

    /**
     * The rootId whose configured domain (tl_page.dns) matches $host. Falls back
     * to a single root when the install has exactly one (dns often empty then).
     * Returns 0 when nothing matches.
     */
    public function rootIdForHost(?string $host): int
    {
        $host = strtolower(trim((string) $host));
        $roots = $this->roots();

        if ($host !== '') {
            foreach ($roots as $root) {
                $dns = strtolower($root['dns']);
                if ($dns !== '' && ($dns === $host || $this->hostMatchesDns($host, $dns))) {
                    return $root['id'];
                }
            }
        }

        // Single-root install: the one root serves every host, dns may be blank.
        if (\count($roots) === 1) {
            return $roots[0]['id'];
        }

        return 0;
    }

    /**
     * True when $host is the dns host or a subdomain of it (www.example.com vs
     * example.com). Kept deliberately simple — exact host or dot-suffix match.
     */
    private function hostMatchesDns(string $host, string $dns): bool
    {
        return $host === $dns || str_ends_with($host, '.' . $dns) || str_ends_with($dns, '.' . $host);
    }
}
