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
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Wires our generated WebVTT files into Contao's stock Video element, which
 * renders no <track> of its own. Dependency-free (the Bauplan's "fills zoglo's
 * <track> wiring", without requiring zoglo).
 *
 * Runs on the whole rendered page (modifyFrontendPage) so it works regardless of
 * how the player element is rendered (legacy .html5 or Twig). For every
 * <video> WITHOUT an existing <track>, each local <source> video is matched to a
 * sibling subtitle file following our naming convention (clip.mp4 → clip.de.vtt)
 * and a <track kind="captions"> is injected before </video>. The first language
 * is marked `default` so captions show without the viewer toggling CC.
 *
 * If a video has no sibling VTT (or already has a track), it is left untouched.
 */
final class SubtitleTrackInjector
{
    public function __construct(
        private readonly string $projectDir,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly RequestStack $requestStack,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    #[AsHook('modifyFrontendPage')]
    public function onModifyFrontendPage(string $buffer, string $template): string
    {
        // Scope gate: an unlicensed root keeps Contao's original markup.
        if (!$this->siteStatus->isActive($this->currentRootId())) {
            return $buffer;
        }

        // Cheap early-out: only touch pages that actually contain a video.
        $host = $this->requestStack->getCurrentRequest()?->getHost();
        if (!str_contains($buffer, '</video>')) {
            return $buffer;
        }

        return preg_replace_callback(
            '/<video\b[^>]*>.*?<\/video>/is',
            fn (array $m): string => $this->augment($m[0]),
            $buffer,
        ) ?? $buffer;
    }

    private function augment(string $videoTag): string
    {
        // Respect an existing caption track (native field, zoglo, manual).
        if (stripos($videoTag, '<track') !== false) {
            return $videoTag;
        }

        $tracks = [];
        if (preg_match_all('/<source\b[^>]*\bsrc="([^"]+)"/i', $videoTag, $m) !== false) {
            foreach ($m[1] as $src) {
                foreach ($this->siblingsFor($src) as $lang => $url) {
                    $tracks[$lang] = $url; // dedupe by language across formats
                }
            }
        }

        if ($tracks === []) {
            return $videoTag;
        }

        $html = '';
        $default = true;
        foreach ($tracks as $lang => $url) {
            $html .= sprintf(
                '<track kind="captions" src="%s" srclang="%s" label="%s"%s>',
                htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars(strtoupper($lang), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $default ? ' default' : '',
            );
            $default = false;
        }

        return preg_replace('/<\/video>/', $html . '</video>', $videoTag, 1) ?? $videoTag;
    }

    /**
     * Find existing sibling subtitle files for a video source.
     *
     * @return array<string, string> Map of language code => track URL
     */
    private function siblingsFor(string $src): array
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (!\is_string($path) || $path === '') {
            return [];
        }

        $relative = ltrim(rawurldecode($path), '/');
        if ($relative === '' || str_contains($relative, '..') || !str_starts_with($relative, 'files/')) {
            return [];
        }

        $dir = \dirname($relative);
        $base = pathinfo($relative, PATHINFO_FILENAME);
        if ($base === '') {
            return [];
        }

        $absDir = $this->projectDir . '/' . $dir;
        $matches = glob($absDir . '/' . $this->escapeGlob($base) . '.*.vtt');
        if ($matches === false || $matches === []) {
            return [];
        }

        $urlDir = $this->dirUrl($path);

        $out = [];
        foreach ($matches as $absVtt) {
            $file = basename($absVtt);
            $lang = $this->langFromVtt($base, $file);
            if ($lang === null) {
                continue;
            }
            $out[$lang] = ($urlDir === '' ? '' : $urlDir) . '/' . rawurlencode($file);
        }

        return $out;
    }

    private function langFromVtt(string $base, string $vttFile): ?string
    {
        $prefix = $base . '.';
        if (!str_starts_with($vttFile, $prefix) || !str_ends_with($vttFile, '.vtt')) {
            return null;
        }

        $lang = strtolower(substr($vttFile, \strlen($prefix), -4));

        return preg_match('/^[a-z]{2,3}([_-][a-z]{2,4})?$/', $lang) === 1 ? $lang : null;
    }

    private function dirUrl(string $path): string
    {
        $dir = \dirname($path);

        return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
    }

    private function escapeGlob(string $value): string
    {
        return str_replace(['*', '?', '[', ']'], ['[*]', '[?]', '[[]', '[]]'], $value);
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
