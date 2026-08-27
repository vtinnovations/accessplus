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

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * "Auf der Seite zeigen": a standalone preview that loads a finding's page in a
 * same-origin iframe and outlines the offending element (scroll + red border)
 * plus a banner with the concrete problem. Opened in a new tab from the report.
 *
 * Security: read-only GET, behind the backend firewall. The target URL and the
 * CSS selector are looked up by finding ID from our own DB (never taken from the
 * request), so there is no SSRF surface; the selector is only ever passed to
 * querySelector() (no eval), and the message is HTML-escaped.
 */
final class HighlightController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SiteStatusProvider $siteStatus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $id = (int) $request->query->get('id', 0);
        $finding = $id > 0 ? FindingModel::findById($id) : null;

        if ($finding === null || $finding->sourceType !== 'frontend' || (string) $finding->sampleUrl === '') {
            return new Response($this->page('<p>' . $this->esc(Text::get('highlight.no_preview')) . '</p>'), 200);
        }

        // Scope gate: the preview belongs to the finding's own site root. A
        // finding that could not be attributed to a root (rootId 0) follows the
        // same rule the linter uses for install-wide findings.
        $rootId = (int) $finding->rootId;
        $licensed = $rootId > 0 ? $this->siteStatus->isActive($rootId) : $this->siteStatus->hasAnyActive();

        if (!$licensed) {
            return new Response($this->page('<p>' . $this->esc(Text::get('highlight.no_preview')) . '</p>'), 200);
        }

        $url = (string) $finding->sampleUrl;
        $selector = (string) $finding->elementLabel;
        $message = (string) $finding->message;

        // axe ships English text — show the German explanation for known rules.
        if (str_starts_with((string) $finding->checkId, 'axe:')) {
            $german = \VTInnovations\AccessPlus\Frontend\AxeMessages::german(substr((string) $finding->checkId, 4));
            if ($german !== null) {
                $message = $german['title'];
            }
        }

        $cfg = json_encode(
            ['url' => $url, 'selector' => $selector, 'message' => $message],
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );

        $banner = '<div id="b" style="padding:10px 14px;background:#1d1d1d;color:#fff;font:14px sans-serif;">'
            . '<strong>' . $this->esc($message) . '</strong><br>'
            . '<span style="color:#aaa;">' . $this->esc(Text::get('highlight.element_label')) . ' ' . $this->esc($selector) . '</span>'
            . ' <span id="s" style="color:#e74c3c;"></span></div>';

        $notes = json_encode([
            'crossOrigin' => Text::get('highlight.cross_origin_note'),
            'notFound' => Text::get('highlight.element_not_found_note'),
            'marked' => Text::get('highlight.marked_note'),
            'impossible' => Text::get('highlight.preview_not_possible_note'),
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $body = $banner
            . '<iframe id="f" src="' . $this->esc($url) . '" style="width:100%;height:calc(100vh - 64px);border:0;"></iframe>'
            . '<script>(function(){var c=' . $cfg . ';var n=' . $notes . ';var f=document.getElementById("f");'
            . 'function note(t){document.getElementById("s").textContent=t;}'
            . 'f.onload=function(){try{var d=f.contentDocument;if(!d){note(n.crossOrigin);return;}'
            . 'var el=d.querySelector(c.selector);if(!el){note(n.notFound);return;}'
            . 'el.scrollIntoView({block:"center"});el.style.outline="4px solid #e74c3c";el.style.outlineOffset="2px";'
            . 'note(n.marked);}catch(e){note(n.impossible);}};})();</script>';

        return new Response($this->page($body), 200);
    }

    private function page(string $body): string
    {
        $lang = $this->esc((string) ($GLOBALS['TL_LANGUAGE'] ?? 'en'));

        return '<!DOCTYPE html><html lang="' . $lang . '"><head><meta charset="utf-8">'
            . '<meta name="robots" content="noindex"><title>' . Text::get('highlight.page_title') . '</title>'
            . '<style>html,body{margin:0;padding:0;background:#fff;}</style></head><body>' . $body . '</body></html>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
