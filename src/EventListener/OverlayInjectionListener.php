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

namespace VTInnovations\AccessPlus\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\LayoutModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\Overlay\OverlayFeatures;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Injects the opt-in comfort overlay into every frontend page when enabled.
 * Assets are bundled locally — no external loader, no inline script, no tokens,
 * no tracking (the project guidelines §3.5). Disabled by default.
 *
 * The CSS/JS are emitted as raw <link>/<script> tags in TL_BODY (NOT TL_CSS/
 * TL_JAVASCRIPT): Contao's Combiner rejects a query string, but we need ?v= to
 * reliably bust the browser cache (combine is often off). TL_BODY is raw HTML,
 * so the version query is safe there. External <script> + <link> stay CSP-safe.
 *
 * Enabled features + accent colour + position travel via the mount div's data-*
 * attributes (no inline script).
 */
final class OverlayInjectionListener
{
    /** Bump when overlay CSS/JS changes (cache-bust via ?v= in TL_BODY). */
    private const ASSET_VERSION = '1.24.4';

    public function __construct(
        private readonly RuntimeConfig $runtimeConfig,
        private readonly RequestStack $requestStack,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    #[AsHook('generatePage')]
    public function onGeneratePage(PageModel $page, LayoutModel $layout, object $pageRegular = null): void
    {
        // Modell 2: the overlay is toggled per site root. rootId comes straight off
        // the page passed to generatePage — no lookup needed.
        $rootId = (int) $page->rootId;

        // Scope gate: without a licence for this root the page is delivered
        // exactly as Contao would deliver it without this bundle.
        if (!$this->siteStatus->isActive($rootId)) {
            return;
        }

        if (!(bool) $this->runtimeConfig->getForRoot($rootId, 'overlay_enabled', false)) {
            return;
        }

        $features = $this->runtimeConfig->get('overlay_features', OverlayFeatures::allIds());
        if (!\is_array($features) || $features === []) {
            $features = OverlayFeatures::allIds();
        }
        $features = array_values(array_intersect(OverlayFeatures::allIds(), $features));
        $dataFeatures = $this->esc(implode(',', $features));

        $color = (string) $this->runtimeConfig->get('overlay_btn_color', '#1d4ed8');
        $color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $color) === 1 ? $color : '#1d4ed8';

        $allowedPos = ['bottomright', 'bottomleft', 'topright', 'topleft', 'middleright', 'middleleft'];
        $position = (string) $this->runtimeConfig->get('overlay_position', 'bottomright');
        $position = \in_array($position, $allowedPos, true) ? $position : 'bottomright';

        $request = $this->requestStack->getCurrentRequest();
        $base = $request !== null ? $request->getBasePath() : '';
        $assetBase = $base . '/bundles/vtinnovationsaccessplus/';
        $v = '?v=' . self::ASSET_VERSION;

        $GLOBALS['TL_BODY'][] = '<link rel="stylesheet" href="' . $this->esc($assetBase . 'accessplus-overlay.css' . $v) . '">'
            . '<div id="accessplus-overlay-root" data-features="' . $dataFeatures . '"'
            . ' data-color="' . $this->esc($color) . '"'
            . ' data-position="' . $this->esc($position) . '"></div>'
            . '<script src="' . $this->esc($assetBase . 'accessplus-overlay.js' . $v) . '" defer></script>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
