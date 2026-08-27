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
use Contao\Model\Collection;
use Symfony\Component\HttpFoundation\RequestStack;
use VTInnovations\AccessPlus\Model\SimplificationModel;
use VTInnovations\AccessPlus\Simplify\SimplifyService;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Swaps approved plain/easy-language drafts into the frontend when the visitor
 * has the language switch active (cookie accessplus_simple = einfach|leicht).
 *
 * Works by SUBSTITUTION on the rendered page (modifyFrontendPage): each approved
 * draft's original snippet text is replaced with its simplified version, once.
 * This is template-agnostic — it covers core text elements AND theme/RockSolid
 * custom elements (icon boxes, teasers, sliders) that render via their own
 * templates and never expose a ->text property. Snippets without an approved
 * draft are left untouched (graceful partial coverage).
 *
 * The original tl_content is never modified — render-time only. Requests with the
 * cookie are private (not shared-cached), so swapped pages do not poison caches.
 */
final class SimpleLanguageRenderer
{
    private const COOKIE = 'accessplus_simple';

    public function __construct(
        private readonly RuntimeConfig $runtimeConfig,
        private readonly RequestStack $requestStack,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    #[AsHook('modifyFrontendPage')]
    public function onModifyFrontendPage(string $buffer, string $template): string
    {
        // Scope gate: text substitution only happens on a licensed root.
        if (!$this->siteStatus->isActive($this->currentRootId())) {
            return $buffer;
        }

        $register = $this->activeRegister();
        if ($register === null) {
            return $buffer;
        }

        $drafts = SimplificationModel::findBy(
            ['register=?', 'lang=?', 'status=?'],
            [$register, $this->currentLang(), 'approved'],
        );
        if (!$drafts instanceof Collection) {
            return $buffer;
        }

        // Longest source first, so a phrase is replaced before any substring of it.
        $rows = [];
        foreach ($drafts as $d) {
            $src = trim((string) $d->sourceText);
            $draft = trim((string) $d->draft);
            if ($src !== '' && $draft !== '') {
                $rows[] = [$src, $draft];
            }
        }
        usort($rows, static fn (array $a, array $b): int => mb_strlen($b[0]) <=> mb_strlen($a[0]));

        foreach ($rows as [$src, $draft]) {
            $buffer = $this->replaceSnippet($buffer, $src, $draft);
        }

        return $buffer;
    }

    private function replaceSnippet(string $buffer, string $src, string $draft): string
    {
        // Swap ONLY the visible text content, never tags: match the source's
        // plain text in the page and replace it with the plain simplified text,
        // leaving all surrounding markup (<p>, <h2>, attributes) intact. The
        // draft is already plain; strip defensively. Try raw + entity encodings
        // so entity-encoded "&" in the rendered page still matches.
        $srcPlain = trim(html_entity_decode(strip_tags($src), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $inline = trim(strip_tags($draft));
        if ($srcPlain === '' || $inline === '' || mb_strlen($srcPlain) < 8) {
            return $buffer;
        }

        $candidates = [
            [htmlspecialchars($srcPlain, ENT_QUOTES, 'UTF-8'), htmlspecialchars($inline, ENT_QUOTES, 'UTF-8')],
            [htmlspecialchars($srcPlain, ENT_NOQUOTES, 'UTF-8'), htmlspecialchars($inline, ENT_NOQUOTES, 'UTF-8')],
            [$srcPlain, $this->esc($inline)],
        ];

        foreach ($candidates as [$needle, $repl]) {
            if ($needle !== '' && str_contains($buffer, $needle)) {
                return $this->replaceFirst($buffer, $needle, $repl);
            }
        }

        return $buffer;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function replaceFirst(string $haystack, string $needle, string $replacement): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replacement, $pos, \strlen($needle));
    }

    private function activeRegister(): ?string
    {
        if (!(bool) $this->runtimeConfig->get('simple_enabled', false)) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $value = (string) $request->cookies->get(self::COOKIE, '');

        return \in_array($value, SimplifyService::REGISTERS, true) ? $value : null;
    }

    private function currentLang(): string
    {
        $page = $GLOBALS['objPage'] ?? null;
        $lang = \is_object($page) && isset($page->language) ? (string) $page->language : (string) ($GLOBALS['TL_LANGUAGE'] ?? 'de');

        return strtolower(substr($lang, 0, 2)) ?: 'de';
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
