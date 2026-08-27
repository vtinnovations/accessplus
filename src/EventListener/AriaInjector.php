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
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\Model\AriaFixModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Applies approved accessible-name (aria-label) fixes at runtime. For each
 * approved fix we emit selector → value; a small local script sets the attribute
 * ONLY where the element is still unnamed (never overriding an existing name or
 * visible text). Fixes are emitted on every page — a selector that does not match
 * the current page is simply a no-op — so a header/footer fix applies site-wide.
 *
 * CSP-safe: the data travels in a <script type="application/json"> block (not
 * executed) and the logic is an external file. No inline JS, no tokens, no
 * tracking. Untrusted values are JSON-encoded (tags hex-escaped) and only ever
 * reach the DOM through setAttribute.
 */
final class AriaInjector
{
    private const ASSET_VERSION = '1.27.0';
    private const CAP = 500;

    public function __construct(
        private readonly RuntimeConfig $runtimeConfig,
        private readonly RequestStack $requestStack,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    #[AsHook('modifyFrontendPage')]
    public function onModifyFrontendPage(string $buffer, string $template): string
    {
        // Scope gate: no licence for this root means no injected markup at all.
        if (!$this->siteStatus->isActive($this->currentRootId())) {
            return $buffer;
        }

        $request = $this->requestStack->getCurrentRequest();
        $host = $request?->getHost();

        if (!str_contains($buffer, '</body>')) {
            return $buffer;
        }

        $approved = AriaFixModel::findByStatus('approved');
        if ($approved === null) {
            return $buffer;
        }

        $map = [];
        foreach ($approved as $fix) {
            $selector = (string) $fix->selector;
            $value = trim((string) $fix->value);
            if ($selector === '' || $value === '' || \count($map) >= self::CAP) {
                continue;
            }
            $map[] = [
                's' => $selector,
                'a' => (string) ($fix->attribute ?: 'aria-label'),
                'v' => $value,
            ];
        }

        if ($map === []) {
            return $buffer;
        }

        $json = json_encode($map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return $buffer;
        }

        $base = $request !== null ? $request->getBasePath() : '';
        $assetBase = $base . '/bundles/vtinnovationsaccessplus/';

        $inject = '<script type="application/json" id="accessplus-aria-data">' . $json . '</script>'
            . '<script src="' . $this->esc($assetBase . 'accessplus-aria.js?v=' . self::ASSET_VERSION) . '" defer></script>';

        // Inject just before </body> so target elements already exist.
        $pos = strripos($buffer, '</body>');
        if ($pos === false) {
            return $buffer;
        }

        return substr($buffer, 0, $pos) . $inject . substr($buffer, $pos);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Current site root in the frontend. Contao exposes the resolved page
     * globally during rendering; 0 when there is none (e.g. an error page).
     */
    private function currentRootId(): int
    {
        return isset($GLOBALS['objPage']) ? (int) ($GLOBALS['objPage']->rootId ?? 0) : 0;
    }
}
