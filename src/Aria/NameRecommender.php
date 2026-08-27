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

namespace VTInnovations\AccessPlus\Aria;

use VTInnovations\AccessPlus\Ai\AiClientFactory;
use VTInnovations\AccessPlus\Ai\AiClientInterface;
use VTInnovations\AccessPlus\Ai\PromptBundle;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Suggests an accessible name (aria-label) for an element that axe reported as
 * unnamed. The primary heuristic already runs in the browser during the scan
 * (where the live DOM is available); this server-side copy is the fallback and
 * powers the optional "improve with AI" action, which works from the stored
 * outer-HTML snippet only (data minimisation — the project guidelines §3.2).
 */
final class NameRecommender
{
    private const MAX_TOKENS = 60;

    /** @var array<string, string> host fragment => human label */
    private const SOCIAL = [
        'facebook.' => 'Facebook', 'fb.com' => 'Facebook', 'instagram.' => 'Instagram',
        'twitter.' => 'Twitter/X', 'x.com' => 'Twitter/X', 'youtube.' => 'YouTube',
        'youtu.be' => 'YouTube', 'linkedin.' => 'LinkedIn', 'xing.' => 'Xing',
        'tiktok.' => 'TikTok', 'pinterest.' => 'Pinterest', 'whatsapp.' => 'WhatsApp',
        'wa.me' => 'WhatsApp', 'vimeo.' => 'Vimeo',
    ];

    public function __construct(
        private readonly AiClientFactory $factory,
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    /**
     * Regex-based best-effort name from an element's outer HTML. Never throws;
     * returns '' when nothing sensible can be derived.
     */
    public function heuristic(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // title attribute wins.
        if (preg_match('/\btitle\s*=\s*"([^"]+)"/i', $html, $m) === 1 && trim($m[1]) !== '') {
            return $this->clean($m[1]);
        }

        // Non-empty img alt inside the element.
        if (preg_match('/<img\b[^>]*\balt\s*=\s*"([^"]+)"/i', $html, $m) === 1 && trim($m[1]) !== '') {
            return $this->clean($m[1]);
        }

        // aria-label already on a child (rare) or a mail/tel link.
        if (preg_match('/href\s*=\s*"mailto:([^"?]+)/i', $html, $m) === 1) {
            return 'E-Mail: ' . $this->clean($m[1]);
        }
        if (preg_match('/href\s*=\s*"tel:([^"?]+)/i', $html, $m) === 1) {
            return 'Anruf: ' . $this->clean($m[1]);
        }

        // iframe / link → derive from the URL.
        if (preg_match('/\bsrc\s*=\s*"([^"]+)"/i', $html, $m) === 1 && stripos($html, '<iframe') !== false) {
            return $this->fromUrl($m[1], 'Eingebetteter Inhalt');
        }
        if (preg_match('/\bhref\s*=\s*"([^"]+)"/i', $html, $m) === 1) {
            $name = $this->fromUrl($m[1], '');
            if ($name !== '') {
                return $name;
            }
        }

        // Fallback: visible text content, stripped.
        $text = $this->clean(strip_tags($html));

        return $text !== '' ? mb_substr($text, 0, 80) : '';
    }

    /**
     * Optional AI refinement of a name from the element's HTML snippet. Returns
     * '' on any problem (egress blocked, provider error) — the caller keeps the
     * heuristic. The snippet is the only data sent.
     */
    public function improve(string $html, string $lang = 'de'): string
    {
        $html = trim(mb_substr($html, 0, 600));
        if ($html === '') {
            return '';
        }

        $client = $this->factory->default();

        $system = sprintf(
            'Du bist Barrierefreiheits-Expertin. Formuliere einen KURZEN, präzisen barrierefreien '
            . 'Namen (aria-label) in der Sprache "%s" für das folgende HTML-Element. '
            . 'Antworte NUR mit dem Namen selbst, ohne Anführungszeichen, ohne Erklärung, '
            . 'unter 80 Zeichen, keine Wörter wie "Link" oder "Button".',
            $lang,
        );

        $bundle = new PromptBundle(
            systemPrompt: $system,
            userPrompt: $html,
            model: $this->modelFor($client),
            temperature: 0.2,
            maxTokens: self::MAX_TOKENS,
            purpose: 'aria_name',
        );

        try {
            $response = $client->complete($bundle);
        } catch (\Throwable) {
            return '';
        }

        return $this->clean(strip_tags($response->content));
    }

    private function fromUrl(string $url, string $fallback): string
    {
        $lower = strtolower($url);
        foreach (self::SOCIAL as $needle => $label) {
            if (str_contains($lower, $needle)) {
                return $label;
            }
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $segment = trim($path, '/');
        if ($segment !== '') {
            $last = basename($segment);
            $last = preg_replace('/\.[a-z0-9]{1,5}$/i', '', $last) ?? $last;   // drop extension
            $last = str_replace(['-', '_'], ' ', rawurldecode($last));
            $last = trim($last);
            if ($last !== '') {
                return $this->clean(ucfirst($last));
            }
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return $host !== '' ? $this->clean($host) : $fallback;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function modelFor(AiClientInterface $client): string
    {
        $model = trim((string) $this->runtimeConfig->get('ai_model', ''));

        return $model !== '' ? $model : $client->defaultModel();
    }
}
