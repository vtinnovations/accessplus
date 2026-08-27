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

namespace VTInnovations\AccessPlus\Frontend;

use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\State\RootScope;

/**
 * Lists the absolute frontend URLs of published, public, regular pages — the
 * scan targets for the axe frontend scanner.
 *
 * Protected pages are skipped (their iframe would just render a login wall).
 * Capped to keep the client-side sweep bounded; the cap is reported to the UI,
 * never silent.
 */
final class PageUrlProvider
{
    public const CAP = 300;

    /**
     * Newest detail items sampled per dynamic list type (news, events). The
     * generated detail pages all share one reader template, so a small sample is
     * enough to catch template-level a11y issues without scanning thousands.
     */
    private const DYNAMIC_SAMPLE = 25;

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly RootScope $rootScope,
    ) {
    }

    /**
     * @param int $rootId Restrict to pages of one site root (Modell 2). 0 = all.
     *
     * @return list<array{id: int, title: string, url: string}>
     */
    public function publishedPages(int $rootId = 0): array
    {
        $now = time();

        // The scan iframe is same-origin to the backend, which is served over
        // HTTPS. Contao's getAbsoluteUrl() honours the root page's useSSL flag —
        // if that is off, it emits http:// URLs that then redirect or trip mixed
        // content. Align the scheme to the backend request so same-origin pages
        // are reached directly over https.
        $request = $this->requestStack->getCurrentRequest();
        $forceHttps = $request !== null && $request->isSecure();

        // When scoping to a root, fetch a wider window (the root's pages may sit
        // beyond the first CAP rows) and filter by the page's root below.
        $sqlLimit = $rootId > 0 ? 5000 : (self::CAP + 1);

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title FROM tl_page
             WHERE type = 'regular' AND published = '1' AND protected != '1'
               AND (start = '' OR start <= :now)
               AND (stop = '' OR stop > :now)
             ORDER BY sorting ASC
             LIMIT " . $sqlLimit,
            ['now' => (string) $now],
        );

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];

            if ($rootId > 0 && $this->rootScope->rootIdForPage($id) !== $rootId) {
                continue;
            }

            $page = PageModel::findByPk($id);
            if ($page === null) {
                continue;
            }

            try {
                $url = $page->getAbsoluteUrl();
            } catch (\Throwable) {
                // No domain configured / unroutable — skip rather than fail.
                continue;
            }

            $out[] = [
                'id' => $id,
                'title' => (string) ($row['title'] ?? ''),
                'url' => $this->normaliseScheme($url, $forceHttps),
            ];

            if (\count($out) >= self::CAP) {
                break;
            }
        }

        // Append a sample of dynamically generated detail pages (news, events)
        // with the remaining budget, so the reader templates get covered too.
        $remaining = self::CAP - \count($out);
        if ($remaining > 0) {
            foreach ($this->dynamicDetailPages($forceHttps, $remaining, $rootId) as $detail) {
                $out[] = $detail;
            }
        }

        return $out;
    }

    /**
     * Sample the newest detail URLs of Contao's core list types (news, calendar
     * events). Each item's URL is its archive/calendar reader page plus the item
     * alias (auto_item). Missing tables/bundles are skipped silently.
     *
     * @return list<array{id: int, title: string, url: string}>
     */
    private function dynamicDetailPages(bool $forceHttps, int $limit, int $rootId = 0): array
    {
        $sources = [
            ['table' => 'tl_news', 'parent' => 'tl_news_archive', 'title' => 'headline', 'order' => 'date', 'label' => 'News'],
            ['table' => 'tl_calendar_events', 'parent' => 'tl_calendar', 'title' => 'title', 'order' => 'startTime', 'label' => 'Termin'],
        ];

        $out = [];
        foreach ($sources as $src) {
            if (\count($out) >= $limit) {
                break;
            }
            $take = min(self::DYNAMIC_SAMPLE, $limit - \count($out));

            try {
                $rows = $this->connection->fetchAllAssociative(
                    'SELECT t.id AS itemId, t.alias AS alias, t.' . $src['title'] . ' AS title, p.jumpTo AS jumpTo
                     FROM ' . $src['table'] . ' t
                     INNER JOIN ' . $src['parent'] . ' p ON p.id = t.pid
                     WHERE t.published = \'1\' AND p.jumpTo > 0
                     ORDER BY t.' . $src['order'] . ' DESC
                     LIMIT ' . (int) $take,
                );
            } catch (\Throwable) {
                // Bundle/table not installed → no such dynamic type here.
                continue;
            }

            foreach ($rows as $row) {
                $jumpTo = (int) $row['jumpTo'];
                if ($rootId > 0 && $this->rootScope->rootIdForPage($jumpTo) !== $rootId) {
                    continue;
                }

                $reader = PageModel::findByPk($jumpTo);
                if ($reader === null) {
                    continue;
                }

                $alias = (string) ($row['alias'] ?? '');
                if ($alias === '') {
                    $alias = (string) (int) $row['itemId'];
                }

                try {
                    $url = $reader->getAbsoluteUrl('/' . $alias);
                } catch (\Throwable) {
                    continue;
                }

                $out[] = [
                    'id' => (int) $row['jumpTo'],
                    'title' => $src['label'] . ': ' . (string) ($row['title'] ?? ''),
                    'url' => $this->normaliseScheme($url, $forceHttps),
                ];
            }
        }

        return $out;
    }

    private function normaliseScheme(string $url, bool $forceHttps): string
    {
        if ($forceHttps && str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }

        return $url;
    }

    public function wasCapped(): bool
    {
        return \count($this->publishedPagesRaw()) > self::CAP;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedPagesRaw(): array
    {
        $now = time();

        return $this->connection->fetchAllAssociative(
            "SELECT id FROM tl_page
             WHERE type = 'regular' AND published = '1' AND protected != '1'
               AND (start = '' OR start <= :now) AND (stop = '' OR stop > :now)",
            ['now' => (string) $now],
        );
    }
}
