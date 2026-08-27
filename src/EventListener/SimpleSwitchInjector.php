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
use VTInnovations\AccessPlus\Simplify\SimplifyService;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Injects the plain/easy-language switch assets when the feature is enabled.
 * Self-contained, local assets — no external loader, no inline script, no
 * tracking (the project guidelines §3.5). The switch sets the accessplus_simple cookie and
 * reloads; SimpleLanguageRenderer then swaps approved drafts in place.
 *
 * One asset bundle drives all placements (floating button, overlay-embedded
 * control, nav-link enhancement); the mount div's data-* attributes decide which
 * are active. Raw tags in TL_BODY with ?v= (Combiner-safe cache-bust), mirroring
 * the overlay injector.
 */
final class SimpleSwitchInjector
{
    private const ASSET_VERSION = '1.15.1';
    private const COOKIE = 'accessplus_simple';

    public function __construct(
        private readonly RuntimeConfig $runtimeConfig,
        private readonly RequestStack $requestStack,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    #[AsHook('generatePage')]
    public function onGeneratePage(PageModel $page, LayoutModel $layout, object $pageRegular = null): void
    {
        // Modell 2: plain-language switch is toggled per site root.
        $rootId = (int) $page->rootId;

        // Scope gate: an unlicensed root gets Contao's untouched page.
        if (!$this->siteStatus->isActive($rootId)) {
            return;
        }

        if (!(bool) $this->runtimeConfig->getForRoot($rootId, 'simple_enabled', false)) {
            return;
        }

        $registers = $this->runtimeConfig->get('simple_registers', SimplifyService::REGISTERS);
        if (!\is_array($registers) || $registers === []) {
            $registers = SimplifyService::REGISTERS;
        }
        $registers = array_values(array_intersect(SimplifyService::REGISTERS, $registers));

        $showButton = (bool) $this->runtimeConfig->get('simple_switch_button', true);
        $showOverlay = (bool) $this->runtimeConfig->get('simple_switch_overlay', true);

        $request = $this->requestStack->getCurrentRequest();
        $base = $request !== null ? $request->getBasePath() : '';
        $active = $request !== null ? (string) $request->cookies->get(self::COOKIE, '') : '';
        if (!\in_array($active, SimplifyService::REGISTERS, true)) {
            $active = '';
        }

        $assetBase = $base . '/bundles/vtinnovationsaccessplus/';
        $v = '?v=' . self::ASSET_VERSION;

        $GLOBALS['TL_BODY'][] = '<link rel="stylesheet" href="' . $this->esc($assetBase . 'accessplus-simple.css' . $v) . '">'
            . '<div id="accessplus-simple-root"'
            . ' data-registers="' . $this->esc(implode(',', $registers)) . '"'
            . ' data-active="' . $this->esc($active) . '"'
            . ' data-button="' . ($showButton ? '1' : '0') . '"'
            . ' data-overlay="' . ($showOverlay ? '1' : '0') . '"'
            . ' data-cookie="' . self::COOKIE . '"></div>'
            . '<script src="' . $this->esc($assetBase . 'accessplus-simple.js' . $v) . '" defer></script>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
