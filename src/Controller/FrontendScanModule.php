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
use VTInnovations\AccessPlus\Frontend\PageUrlProvider;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Backend module that bootstraps the browser-side axe scanner (BE_MOD callback).
 *
 * It only RENDERS: it lists the published pages, embeds a same-origin iframe and
 * the bundled axe-core + orchestrator JS, and hands the JS the page list, the
 * axe asset URL, the ingest route and the request token. All scanning happens in
 * the browser; results flow to FrontendScanController. Pure GET — no PRG needed.
 */
final class FrontendScanModule
{
    use BackendModuleHelper;

    /** Bump when Resources/public/accessplus-scan.js changes (cache-bust). */
    private const ASSET_VERSION = '1.8.0';

    public function generate(): string
    {
        $container = System::getContainer();

        /** @var PageUrlProvider $provider */
        $provider = $container->get(PageUrlProvider::class);
        $pages = $provider->publishedPages();

        $router = $container->get('router');

        // Build asset URLs from the request base path (handles sub-directory
        // installs; avoids depending on the non-public assets.packages service).
        $request = $this->currentRequest();
        $base = $request instanceof Request ? $request->getBasePath() : '';
        $assetBase = $base . '/bundles/vtinnovationsaccessplus/';
        // Cache-bust: browsers cache the scanner JS aggressively. Bump on change
        // so a new version is fetched instead of a stale (pre-scanStart) one.
        $v = '?v=' . self::ASSET_VERSION;

        // Focus on legally MANDATORY rules only (WCAG 2.x A/AA = BITV 2.0 / BFSG
        // via EN 301 549). axe runs only these tags, so best-practice/landmark
        // and AAA noise is never produced. Tied to the WCAG-target setting.
        $wcagTarget = (string) $container->get(RuntimeConfig::class)->get('wcag_target', 'AA');
        $axeTags = $this->axeTagsForTarget($wcagTarget);

        $config = [
            'pages' => $pages,
            'axeUrl' => $assetBase . 'axe.min.js' . $v,
            'ingestUrl' => $router->generate('vtinnovations_accessplus_axe_ingest'),
            'token' => $this->requestToken(),
            'axeTags' => $axeTags,
        ];

        $scriptUrl = $assetBase . 'accessplus-scan.js' . $v;

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('frontend_scan.title') . '</h1>';

        if ($pages === []) {
            $html .= '<p class="tl_info">' . $this->esc($this->trans('frontend_scan.no_pages')) . '</p>';

            return $this->wrap($html);
        }

        $html .= '<p class="tl_info">' . $this->trans('frontend_scan.help', [
            'count' => \count($pages),
            'target' => $this->esc($wcagTarget),
        ]);
        if ($provider->wasCapped()) {
            $html .= $this->trans('frontend_scan.capped_note', ['cap' => PageUrlProvider::CAP]);
        }
        $html .= '</p>';

        $html .= '<p style="color:#999;">' . $this->trans('frontend_scan.session_hint') . '</p>';

        $html .= '<div style="margin:14px 0;">';
        $html .= '<button type="button" id="accessplus-scan-start" class="tl_submit">' . $this->esc($this->trans('frontend_scan.start_btn')) . '</button>';
        $html .= ' <span id="accessplus-scan-progress" style="margin-left:10px;color:#36a957;"></span>';
        $html .= '</div>';

        // Hidden same-origin scan iframe (NOT sandboxed — we must inject axe).
        $html .= '<iframe id="accessplus-scan-frame" title="' . $this->esc($this->trans('common.scan_word')) . '" style="width:1280px;height:1px;opacity:0;position:absolute;left:-9999px;border:0;"></iframe>';

        $html .= '<script>window.VTA11Y_SCAN = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) . ';</script>';
        $html .= '<script src="' . $this->esc($scriptUrl) . '"></script>';

        return $this->wrap($html);
    }

    /**
     * axe rule tags for the mandatory conformance level (BITV/BFSG = WCAG 2.x
     * A+AA via EN 301 549). Best-practice / experimental rules are excluded by
     * passing only these tags to axe.run({runOnly}).
     *
     * @return list<string>
     */
    private function axeTagsForTarget(string $target): array
    {
        return match (strtoupper($target)) {
            'A' => ['wcag2a', 'wcag21a'],
            'AAA' => ['wcag2a', 'wcag2aa', 'wcag2aaa', 'wcag21a', 'wcag21aa', 'wcag21aaa'],
            default => ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'],
        };
    }
}
