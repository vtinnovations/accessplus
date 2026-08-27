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

namespace VTInnovations\AccessPlus\Controller;

use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use VTInnovations\AccessPlus\Check\CheckRegistry;
use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\Dashboard\Category;
use VTInnovations\AccessPlus\Dashboard\CategoryClassifier;
use VTInnovations\AccessPlus\Dashboard\FullAnalysis;
use VTInnovations\AccessPlus\Frontend\PageUrlProvider;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\Model\RunModel;
use VTInnovations\AccessPlus\Monitor\DeltaCalculator;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Phase-4 dashboard (BE_MOD callback). "Voll-Analyse starten" runs every check
 * once and records a run; the result is shown as three honest columns —
 * ✅ erledigt / 🔘 Ein-Klick / 👤 nur manuell — plus the score and run history.
 *
 * POST → PRG redirect (Turbo). Detection + bookkeeping only; applying fixes
 * happens in the dedicated modules (KI-Alt-Texte), reached via links here.
 */
final class DashboardModule
{
    use BackendModuleHelper;

    private const COLUMN_CAP = 50;

    /** Bump when accessplus-fullscan.js / accessplus-scan assets change (cache-bust). */
    private const SCAN_ASSET_VERSION = '1.30.0';

    public function generate(): string
    {
        $request = $this->currentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            $this->handlePost($request);
            $this->redirectToSelf();
        }

        return $this->wrap($this->render());
    }

    /**
     * Combined full scan UI: one button runs the database analysis (AJAX) and
     * then the client-side axe frontend scan, with a progress bar + phase text.
     */
    private function renderFullScan(string $token, int $rootId): string
    {
        $container = System::getContainer();

        $pages = $container->get(PageUrlProvider::class)->publishedPages($rootId);
        $wcagTarget = (string) $container->get(RuntimeConfig::class)->get('wcag_target', 'AA');
        $router = $container->get('router');

        $request = $this->currentRequest();
        $base = $request instanceof Request ? $request->getBasePath() : '';
        $assetBase = $base . '/bundles/vtinnovationsaccessplus/';
        $v = '?v=' . self::SCAN_ASSET_VERSION;

        $config = [
            'dbUrl' => $router->generate('vtinnovations_accessplus_db_analyze'),
            'ingestUrl' => $router->generate('vtinnovations_accessplus_axe_ingest'),
            'ariaUrl' => $router->generate('vtinnovations_accessplus_aria_ingest'),
            'axeUrl' => $assetBase . 'axe.min.js' . $v,
            'token' => $token,
            'axeTags' => $this->axeTagsForTarget($wcagTarget),
            'pages' => $pages,
            'root' => $rootId,
        ];

        $html = '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('dashboard.fullscan_legend')) . '</legend>';
        $html .= '<p class="tl_help" style="margin-top:0;">' . $this->trans('dashboard.fullscan_help') . '</p>';
        $html .= '<div class="tca-toolbar">';
        $html .= '<button type="button" id="accessplus-fs-start" class="tl_submit">' . $this->esc($this->trans('dashboard.fullscan_start_btn')) . '</button>';
        $html .= '<span id="accessplus-fs-phase" style="font-weight:600;"></span>';
        $html .= '</div>';
        $html .= '<div id="accessplus-fs-bar" class="accessplus-bar" hidden><div id="accessplus-fs-fill" class="accessplus-bar__fill"></div></div>';
        $html .= '<p id="accessplus-fs-note" style="color:#c0392b;margin:6px 0 0;"></p>';
        // A realistic viewport (not 1px) so scroll/IntersectionObserver reveal
        // animations actually fire — otherwise pre-reveal styles cause false
        // contrast/visibility violations. Kept offscreen.
        $html .= '<iframe id="accessplus-fs-frame" title="' . $this->esc($this->trans('common.scan_word')) . '" style="width:1280px;height:900px;opacity:0;position:absolute;left:-9999px;top:0;border:0;"></iframe>';
        $html .= '<script>window.VTA11Y_FULLSCAN = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) . ';</script>';
        $html .= '<script src="' . $this->esc($assetBase . 'accessplus-fullscan.js' . $v) . '"></script>';

        // Reliable fallback: a plain server-side database analysis (PRG, no AJAX/
        // route dependency) that always records a run so the score appears.
        $html .= '<form method="post" action="" style="margin-top:10px;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="analyze">';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('dashboard.db_only_btn')) . '</button>';
        $html .= '</form>';
        $html .= $this->renderDomainHint($pages);
        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Multi-domain installs: the iframe frontend scan only covers the domain the
     * backend is opened on. List every domain among the published pages and link
     * to each one's own backend so the editor can scan it there.
     *
     * @param list<array{id:int,url:string,title:string}> $pages
     */
    private function renderDomainHint(array $pages): string
    {
        $request = $this->currentRequest();
        $current = $request instanceof Request ? $request->getSchemeAndHttpHost() : '';

        $origins = [];
        foreach ($pages as $p) {
            $url = (string) ($p['url'] ?? '');
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $host = parse_url($url, PHP_URL_HOST);
            $origin = (\is_string($scheme) && \is_string($host)) ? $scheme . '://' . $host : $current;
            if ($origin === '') {
                continue;
            }
            $origins[$origin] = ($origins[$origin] ?? 0) + 1;
        }

        if (\count($origins) <= 1) {
            return '';
        }

        // This install serves several domains. Frontend features + the frontend
        // scan apply per domain (run the scan from each one); the database
        // analysis is install-wide and independent.
        $html = '<p class="tl_info" style="margin-top:12px;">' . $this->trans('dashboard.multi_domain_hint') . '</p>';
        $html .= '<ul style="margin:6px 0 0;padding-left:18px;list-style:none;">';
        foreach ($origins as $origin => $cnt) {
            $extra = ' · ' . ($origin === $current
                ? $this->esc($this->trans('dashboard.multi_domain_scanned_here'))
                : '<a href="' . $this->esc($origin . '/contao?do=accessplus&tab=dashboard') . '" target="_blank" rel="noopener">' . $this->esc($this->trans('dashboard.multi_domain_open_backend')) . '</a>');

            $html .= '<li style="padding:2px 0;"><strong>' . $this->esc($origin) . '</strong> – '
                . $this->esc($this->trans('dashboard.multi_domain_pages_count', ['count' => $cnt])) . $extra . '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * @return list<string>
     */
    private function axeTagsForTarget(string $target): array
    {
        $tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
        if ($target === 'A') {
            return ['wcag2a', 'wcag21a'];
        }
        if ($target === 'AAA') {
            $tags[] = 'wcag2aaa';
            $tags[] = 'wcag21aaa';
        }

        return $tags;
    }

    private function handlePost(Request $request): void
    {
        if (!$this->isTokenValid($request)) {
            $this->error($this->trans('common.invalid_token'));

            return;
        }

        $action = (string) $request->request->get('accessplus_action', '');

        if ($action === 'analyze') {
            $run = $this->service(FullAnalysis::class)->run($this->currentRoot());
            $this->confirm($this->trans('dashboard.db_analysis_done', [
                'score' => (int) $run->score,
                'oneclick' => (int) $run->countOneClick,
                'manual' => (int) $run->countManual,
                'done' => (int) $run->countDone,
            ]));

            return;
        }

        if ($action === 'reset_frontend') {
            $n = $this->resetFrontendFindings();
            $this->confirm($this->trans('dashboard.reset_frontend_confirm', ['count' => $n]));
        }
    }

    /**
     * Clear all stored frontend (axe) findings — useful when the iframe scan
     * produced false positives (e.g. pre-reveal animation states). The next full
     * scan re-detects whatever is genuinely present.
     */
    private function resetFrontendFindings(): int
    {
        $connection = System::getContainer()->get('database_connection');
        $rootId = $this->currentRoot();

        if ($rootId > 0) {
            return (int) $connection->executeStatement(
                "DELETE FROM tl_accessplus_finding WHERE sourceType = 'frontend' AND rootId = ?",
                [$rootId]
            );
        }

        return (int) $connection->executeStatement("DELETE FROM tl_accessplus_finding WHERE sourceType = 'frontend'");
    }

    private function render(): string
    {
        $token = $this->requestToken();
        $rootId = $this->currentRoot();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('dashboard.title') . '</h1>';

        $html .= $this->renderFullScan($token, $rootId);

        // Always show the live score overview — even before any run is recorded for
        // this root — so a clean domain reads "100 · 0 Befunde" instead of an empty
        // "no analysis yet" message. The stored run is only needed for the trend.
        $html .= $this->renderScoreOverview($rootId, $this->latestRun($rootId));

        $html .= $this->renderColumns($rootId);
        $html .= $this->renderFrontendSection($rootId);
        $html .= $this->renderRunHistory($rootId);

        return $html;
    }

    /**
     * Newest run for the given root (0 = install-wide run, rootId column = 0).
     */
    private function latestRun(int $rootId): ?RunModel
    {
        $runs = RunModel::findBy(['rootId = ?'], [$rootId], ['order' => 'startedAt DESC', 'limit' => 1]);
        if ($runs === null) {
            return null;
        }
        foreach ($runs as $r) {
            return $r;
        }

        return null;
    }

    private function renderScoreOverview(int $rootId, ?RunModel $run): string
    {
        $counts = $this->openCounts($rootId);
        // Honest score from ALL open findings (DB + frontend), weighted by
        // severity. Computed live so it reflects what's actually open right now.
        $score = $this->scoreFromCounts($counts);
        // 3-column counts live too (so the card is correct even without a stored
        // run for this root). The stored run is used only for the trend delta.
        $cat = $this->categoryCounts($rootId);

        // A per-root delta from an install-wide DeltaCalculator would be
        // misleading, so the trend only shows in the install-wide view.
        $delta = $rootId === 0 ? $this->renderDelta() : '';
        $hint = $run === null ? ' <span style="color:#999;font-size:.8em;">' . $this->trans('dashboard.live_hint') . '</span>' : '';

        $html = '<div class="tca-score">';
        $html .= '<div class="tca-score__ring">' . $this->renderScoreRing($score) . '</div>';
        $html .= '<div class="tca-score__body">';
        $html .= '<p style="margin:0 0 4px;"><strong>' . $this->trans('dashboard.score_optimized', ['score' => $score]) . '</strong> ' . $delta . $hint . '</p>';
        $html .= '<p style="margin:0 0 12px;color:#999;">' . $this->trans('dashboard.score_disclaimer') . '</p>';
        $html .= '<div class="tca-stats">';
        $html .= $this->statCard($this->trans('dashboard.stat_open'), (string) $counts['open'], '');
        $html .= $this->statCard($this->trans('dashboard.stat_critical'), (string) $counts['critical'], $counts['critical'] > 0 ? 'bad' : 'good');
        $html .= $this->statCard($this->trans('dashboard.stat_serious'), (string) $counts['serious'], $counts['serious'] > 0 ? 'warn' : 'good');
        $html .= $this->statCard($this->trans('dashboard.stat_oneclick'), (string) $cat['oneClick'], 'info');
        $html .= $this->statCard($this->trans('dashboard.stat_manual'), (string) $cat['manual'], '');
        $html .= $this->statCard($this->trans('dashboard.stat_done'), (string) $cat['done'], 'good');
        $html .= $this->statCard($this->trans('dashboard.stat_frontend'), (string) $counts['frontend'], '');
        $html .= $this->statCard($this->trans('dashboard.stat_image_alt'), (string) $counts['imageAlt'], $counts['imageAlt'] > 0 ? 'warn' : 'good');
        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Live 3-column counts (Ein-Klick / manuell / erledigt) for a root, computed
     * the same way as the run snapshot but from the current findings — so the
     * score card is correct even before any run has been recorded for this root.
     *
     * @return array{oneClick:int, manual:int, done:int}
     */
    private function categoryCounts(int $rootId): array
    {
        $classifier = $this->service(CategoryClassifier::class);
        $out = ['oneClick' => 0, 'manual' => 0, 'done' => 0];

        $openCols = ['(status = ? OR status = ?) AND sourceType != ?'];
        $openVals = [FindingStatus::Open->value, FindingStatus::Confirmed->value, 'frontend'];
        if ($rootId > 0) {
            $openCols[0] .= ' AND rootId = ?';
            $openVals[] = $rootId;
        }
        $open = FindingModel::findBy($openCols, $openVals);
        if ($open !== null) {
            foreach ($open as $finding) {
                if ($classifier->classify((string) $finding->checkId, (string) $finding->status) === Category::OneClick) {
                    ++$out['oneClick'];
                } else {
                    ++$out['manual'];
                }
            }
        }

        $doneCols = ['status = ? AND sourceType != ?'];
        $doneVals = [FindingStatus::Fixed->value, 'frontend'];
        if ($rootId > 0) {
            $doneCols[0] .= ' AND rootId = ?';
            $doneVals[] = $rootId;
        }
        $out['done'] = (int) FindingModel::countBy($doneCols, $doneVals);

        return $out;
    }

    /**
     * @param array{critical:int,serious:int,moderate:int,minor:int,...} $counts
     */
    private function scoreFromCounts(array $counts): int
    {
        $penalty = $counts['critical'] * 4 + $counts['serious'] * 3 + $counts['moderate'] * 2 + $counts['minor'];

        return max(0, min(100, 100 - $penalty));
    }

    private function renderScoreRing(int $score): string
    {
        $r = 52;
        $circ = 2 * M_PI * $r;
        $dash = round($circ * $score / 100, 1);
        $gap = round($circ - $dash, 1);
        $color = $score >= 90 ? '#2f855a' : ($score >= 70 ? '#dd6b20' : '#c53030');

        return '<svg width="140" height="140" viewBox="0 0 140 140" role="img" aria-label="' . $this->esc($this->trans('dashboard.score_ring_aria', ['score' => $score])) . '">'
            . '<circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="rgba(128,128,128,.18)" stroke-width="14"/>'
            . '<circle cx="70" cy="70" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="14" stroke-linecap="round"'
            . ' stroke-dasharray="' . $dash . ' ' . $gap . '" transform="rotate(-90 70 70)"/>'
            . '<text x="70" y="74" text-anchor="middle" font-size="36" font-weight="700" fill="currentColor">' . $score . '</text>'
            . '<text x="70" y="94" text-anchor="middle" font-size="10" letter-spacing="1.5" fill="#8a8a8a">' . $this->esc($this->trans('dashboard.score_ring_caption')) . '</text>'
            . '</svg>';
    }

    private function statCard(string $label, string $value, string $tone): string
    {
        $cls = $tone !== '' ? ' tca-stat--' . $tone : '';

        return '<div class="tca-stat' . $cls . '"><div class="tca-stat__value">' . $this->esc($value) . '</div>'
            . '<div class="tca-stat__label">' . $this->esc($label) . '</div></div>';
    }

    /**
     * @return array{open:int,critical:int,serious:int,moderate:int,minor:int,frontend:int,database:int,imageAlt:int}
     */
    private function openCounts(int $rootId): array
    {
        $out = ['open' => 0, 'critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0,
            'frontend' => 0, 'database' => 0, 'imageAlt' => 0];

        $connection = System::getContainer()->get('database_connection');
        $sm = method_exists($connection, 'createSchemaManager') ? $connection->createSchemaManager() : $connection->getSchemaManager(); // @phpstan-ignore-line
        if (!\in_array('tl_accessplus_finding', array_map('strtolower', $sm->listTableNames()), true)) {
            return $out;
        }

        // rootId is a validated int (cast) → safe to inline in the shared WHERE.
        $where = "status IN ('open', 'confirmed')" . ($rootId > 0 ? ' AND rootId = ' . $rootId : '');

        $out['open'] = (int) $connection->fetchOne('SELECT COUNT(*) FROM tl_accessplus_finding WHERE ' . $where);

        foreach ($connection->fetchAllAssociative('SELECT severity, COUNT(*) AS c FROM tl_accessplus_finding WHERE ' . $where . ' GROUP BY severity') as $row) {
            $sev = strtolower((string) $row['severity']);
            if (isset($out[$sev])) {
                $out[$sev] = (int) $row['c'];
            }
        }

        foreach ($connection->fetchAllAssociative('SELECT sourceType, COUNT(*) AS c FROM tl_accessplus_finding WHERE ' . $where . ' GROUP BY sourceType') as $row) {
            if ((string) $row['sourceType'] === 'frontend') {
                $out['frontend'] = (int) $row['c'];
            } else {
                $out['database'] += (int) $row['c'];
            }
        }

        $out['imageAlt'] = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM tl_accessplus_finding WHERE ' . $where . " AND checkId = 'image_alt_missing'",
        );

        return $out;
    }

    private function renderDelta(): string
    {
        $delta = $this->service(DeltaCalculator::class)->latest();
        if (!$delta->hasPrevious) {
            return '';
        }

        $trend = $delta->scoreDelta > 0 ? '▲ +' . $delta->scoreDelta
            : ($delta->scoreDelta < 0 ? '▼ ' . $delta->scoreDelta : '→ ±0');
        $color = $delta->scoreDelta > 0 ? '#36a957' : ($delta->scoreDelta < 0 ? '#c0392b' : '#999');

        return '<span style="color:' . $color . ';font-size:.8em;font-weight:bold;">' . $trend . '</span>'
            . ' <span style="color:#999;font-size:.8em;">' . $this->trans('dashboard.trend_since', [
                'new' => $delta->newCount,
                'resolved' => $delta->resolvedCount,
            ]) . '</span>';
    }

    private function renderFrontendSection(int $rootId): string
    {
        $columns = ['sourceType = ? AND (status = ? OR status = ?)'];
        $values = ['frontend', FindingStatus::Open->value, FindingStatus::Confirmed->value];
        if ($rootId > 0) {
            $columns[0] .= ' AND rootId = ?';
            $values[] = $rootId;
        }
        $open = FindingModel::findBy($columns, $values, ['order' => 'severity ASC, tstamp DESC']);

        $items = [];
        if ($open !== null) {
            foreach ($open as $f) {
                $items[] = $f;
            }
        }

        $html = '<fieldset class="tl_tbox block" style="margin-top:14px;"><legend>' . $this->trans('dashboard.frontend_section_legend', ['count' => \count($items)]) . '</legend>';
        if ($items === []) {
            return $html . '<p style="color:#999;">' . $this->esc($this->trans('dashboard.frontend_section_empty')) . '</p></fieldset>';
        }

        $html .= '<p class="tl_help" style="margin-top:0;">' . $this->trans('dashboard.frontend_section_help') . '</p>';
        $html .= '<form method="post" action="" style="margin:0 0 8px;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($this->requestToken()) . '">';
        $html .= '<button type="submit" name="accessplus_action" value="reset_frontend" class="tl_submit">' . $this->esc($this->trans('dashboard.reset_frontend_btn')) . '</button>';
        $html .= '</form>';

        foreach (\array_slice($items, 0, self::COLUMN_CAP) as $finding) {
            $rule = str_starts_with((string) $finding->checkId, 'axe:') ? substr((string) $finding->checkId, 4) : '';
            $german = $rule !== '' ? \VTInnovations\AccessPlus\Frontend\AxeMessages::german($rule) : null;
            $title = $german['title'] ?? (string) $finding->message;
            $sev = \VTInnovations\AccessPlus\Check\Severity::tryFrom((string) $finding->severity);

            $html .= '<div style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.15);">';
            if ($sev !== null) {
                $html .= '<span style="display:inline-block;min-width:64px;color:' . $sev->color() . ';font-weight:bold;font-size:.85em;">' . $this->esc($sev->label()) . '</span> ';
            }
            $html .= '<strong>' . $this->esc($title) . '</strong>';
            if ((int) $finding->occurrences > 1) {
                $html .= ' <span style="color:#c0392b;font-weight:bold;">' . $this->esc($this->trans('common.occurrences_pages', ['count' => (int) $finding->occurrences])) . '</span>';
            }
            $html .= '<br><span style="color:#888;font-size:.9em;">' . $this->esc((string) $finding->elementLabel) . '</span>';
            if ((string) $finding->sampleUrl !== '') {
                $html .= ' <a href="' . $this->esc($this->highlightUrl((int) $finding->id)) . '" target="_blank" rel="noopener" style="font-size:.85em;">' . $this->esc($this->trans('common.show_on_page')) . '</a>';
            }
            $html .= '</div>';
        }

        if (\count($items) > self::COLUMN_CAP) {
            $html .= '<p style="color:#999;">' . $this->trans('dashboard.column_more_report', ['count' => \count($items) - self::COLUMN_CAP]) . '</p>';
        }

        return $html . '</fieldset>';
    }

    private function renderColumns(int $rootId): string
    {
        $classifier = $this->service(CategoryClassifier::class);
        $labels = $this->checkLabels();

        $buckets = [Category::Done->value => [], Category::OneClick->value => [], Category::Manual->value => []];

        // The 3 columns reflect the deterministic DATABASE analysis. Frontend
        // (axe) findings live in the Bericht/Report (they vary with scan coverage)
        // and would otherwise make these columns flap.
        $openCols = ['(status = ? OR status = ?) AND sourceType != ?'];
        $openVals = [FindingStatus::Open->value, FindingStatus::Confirmed->value, 'frontend'];
        if ($rootId > 0) {
            $openCols[0] .= ' AND rootId = ?';
            $openVals[] = $rootId;
        }
        $open = FindingModel::findBy($openCols, $openVals, ['order' => 'severity ASC, tstamp DESC']);
        if ($open !== null) {
            foreach ($open as $finding) {
                $category = $classifier->classify((string) $finding->checkId, (string) $finding->status);
                $buckets[$category->value][] = $finding;
            }
        }

        // Only count genuinely-resolved DB/manual findings as "Erledigt". Frontend
        // (axe) findings can come and go with scan coverage, so they must not
        // inflate the done count with phantom fixes.
        $fixedCols = ['status = ? AND sourceType != ?'];
        $fixedVals = [FindingStatus::Fixed->value, 'frontend'];
        if ($rootId > 0) {
            $fixedCols[0] .= ' AND rootId = ?';
            $fixedVals[] = $rootId;
        }
        $fixed = FindingModel::findBy($fixedCols, $fixedVals, ['order' => 'tstamp DESC', 'limit' => self::COLUMN_CAP]);
        if ($fixed !== null) {
            foreach ($fixed as $finding) {
                $buckets[Category::Done->value][] = $finding;
            }
        }

        $html = '<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">';
        foreach ([Category::Done, Category::OneClick, Category::Manual] as $category) {
            $html .= $this->renderColumn($category, $buckets[$category->value], $labels);
        }

        return $html . '</div>';
    }

    /**
     * @param list<FindingModel>    $findings
     * @param array<string, string> $labels
     */
    private function renderColumn(Category $category, array $findings, array $labels): string
    {
        $total = \count($findings);
        $shown = \array_slice($findings, 0, self::COLUMN_CAP);

        $html = '<fieldset class="tl_tbox block" style="flex:1;min-width:280px;">';
        $html .= '<legend>' . $category->icon() . ' ' . $this->esc($category->label()) . ' (' . $total . ')</legend>';

        if ($category === Category::OneClick && $total > 0) {
            $html .= '<p class="tl_info">' . $this->trans('dashboard.category_hint') . '</p>';
        }

        if ($total === 0) {
            return $html . '<p style="color:#999;">' . $this->esc($this->trans('dashboard.column_nothing')) . '</p></fieldset>';
        }

        foreach ($shown as $finding) {
            $checkLabel = $labels[$finding->checkId] ?? (string) $finding->checkId;
            $occ = (int) $finding->occurrences;
            $html .= '<div style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.15);">';
            $html .= '<strong>' . $this->esc($checkLabel) . '</strong>';
            if ((string) $finding->wcagSc === '' && str_starts_with((string) $finding->checkId, 'axe:')) {
                $html .= ' <span style="background:#555;color:#ddd;padding:1px 5px;border-radius:3px;font-size:.75em;" title="' . $this->esc($this->trans('common.best_practice_title')) . '">' . $this->esc($this->trans('common.best_practice_badge')) . '</span>';
            }
            if ($occ > 1) {
                $html .= ' <span style="color:#c0392b;font-weight:bold;">' . $this->esc($this->trans('common.occurrences_pages', ['count' => $occ])) . '</span>';
            }
            $html .= '<br><span style="color:#888;font-size:.9em;">' . $this->esc((string) $finding->elementLabel) . '</span>';
            if ($category === Category::Manual && ($finding->suggestion ?? '') !== '') {
                $html .= '<br><span style="color:#36a957;font-size:.9em;">↳ ' . nl2br($this->esc((string) $finding->suggestion)) . '</span>';
            }
            $editUrl = $this->editUrl((string) $finding->ptable, (int) $finding->pid);
            if ($editUrl !== null) {
                $html .= '<br><a href="' . $this->esc($editUrl) . '" target="_blank" rel="noopener" style="font-size:.85em;color:#36a957;font-weight:bold;">' . $this->esc($this->trans('common.fix_now')) . '</a>';
            }
            if (str_starts_with((string) $finding->checkId, 'axe:') && (string) $finding->sampleUrl !== '') {
                $html .= ' <a href="' . $this->esc($this->highlightUrl((int) $finding->id)) . '" target="_blank" rel="noopener" style="font-size:.85em;">' . $this->esc($this->trans('common.show_on_page')) . '</a>';
            }
            $html .= '</div>';
        }

        if ($total > self::COLUMN_CAP) {
            $html .= '<p style="color:#999;">' . $this->trans('dashboard.column_more', ['count' => $total - self::COLUMN_CAP]) . '</p>';
        }

        return $html . '</fieldset>';
    }

    private function renderRunHistory(int $rootId): string
    {
        $runs = RunModel::findBy(['rootId = ?'], [$rootId], ['order' => 'startedAt DESC', 'limit' => 10]);
        if ($runs === null) {
            return '';
        }

        $html = '<h2 style="margin-top:20px;">' . $this->esc($this->trans('dashboard.run_history_title')) . '</h2>';
        $html .= '<table class="tl_listing"><thead><tr>'
            . '<th class="tl_folder_tlist">' . $this->esc($this->trans('dashboard.run_history_time')) . '</th><th class="tl_folder_tlist">' . $this->esc($this->trans('dashboard.run_history_score')) . '</th>'
            . '<th class="tl_folder_tlist">✅</th><th class="tl_folder_tlist">🔘</th><th class="tl_folder_tlist">👤</th>'
            . '</tr></thead><tbody>';

        foreach ($runs as $run) {
            $html .= '<tr>'
                . '<td>' . $this->esc(date('d.m.Y H:i', (int) $run->startedAt)) . '</td>'
                . '<td>' . (int) $run->score . '</td>'
                . '<td>' . (int) $run->countDone . '</td>'
                . '<td>' . (int) $run->countOneClick . '</td>'
                . '<td>' . (int) $run->countManual . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * @return array<string, string>
     */
    private function checkLabels(): array
    {
        $labels = [];
        foreach ($this->service(CheckRegistry::class)->all() as $check) {
            $labels[$check->getId()] = $check->getLabel();
        }

        return $labels;
    }
}
