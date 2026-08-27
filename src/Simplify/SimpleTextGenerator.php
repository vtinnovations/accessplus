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

namespace VTInnovations\AccessPlus\Simplify;

use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Ai\AiClientFactory;
use VTInnovations\AccessPlus\Ai\AiClientInterface;
use VTInnovations\AccessPlus\Ai\PromptBundle;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Rewrites a passage of HTML into a simplified register (Einfache or Leichte
 * Sprache) via the configured text model.
 *
 * Doing it RIGHT:
 *   - Output is a PROPOSAL, never written live.
 *   - Allowed output tags are whitelisted on the way out (no script/style).
 *   - Data minimisation: only the one passage + the rules leave the server.
 *   - Egress switch + key handling live in the AI client.
 */
final class SimpleTextGenerator
{
    private const MAX_TOKENS = 1500;

    public function __construct(
        private readonly AiClientFactory $factory,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(string $sourceHtml, string $register, string $lang): string
    {
        $client = $this->factory->default();

        $source = trim(html_entity_decode(strip_tags($sourceHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $srcChars = mb_strlen($source);
        // Length budget: never blow up short UI copy (cards/buttons break the
        // layout). Allow a small margin, hard cap so a 30-char card can't become
        // a paragraph. Tokens scale with the budget, not a fixed 1500.
        $maxChars = max(40, (int) round($srcChars * 1.2));
        $maxTokens = (int) min(self::MAX_TOKENS, max(40, ceil($maxChars / 2)));

        $bundle = new PromptBundle(
            systemPrompt: $this->systemPrompt($register, $lang),
            userPrompt: sprintf(
                "Vereinfache diesen Inhalt. Gib NUR den vereinfachten Text zurück — reiner Fließtext "
                . "OHNE HTML-Tags, ohne Markdown, ohne Erklärung, ohne Code-Fences.\n"
                . "WICHTIG: Der vereinfachte Text muss ungefähr gleich lang oder KÜRZER sein als das "
                . "Original (höchstens ~%d Zeichen). Fasse dich knapp, keine zusätzlichen Erklärungen "
                . "oder Wiederholungen. Bei kurzen Texten bleibt das Ergebnis kurz.\n\nOriginal (%d Zeichen):\n%s",
                $maxChars,
                $srcChars,
                $source,
            ),
            model: $this->modelFor($client),
            temperature: 0.3,
            maxTokens: $maxTokens,
            purpose: 'simple_language',
        );

        $response = $client->complete($bundle);
        $html = $this->sanitise($response->content);

        // Preserve ALL-CAPS source casing (e.g. headings/labels written entirely
        // in uppercase) — only normal-case text gets grammatical recasing.
        if ($html !== '' && $this->isAllCaps($source)) {
            $html = mb_strtoupper($html, 'UTF-8');
        }

        if ($html === '') {
            $this->logger->info('a11y simplify: empty/clean-strip result', [
                'provider' => $response->provider,
                'model' => $response->model,
            ]);
        }

        return $html;
    }

    private function isAllCaps(string $text): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        if ($letters === '') {
            return false;
        }

        // Uppercase equals itself AND there are cased letters (lowercased differs).
        return $letters === mb_strtoupper($letters, 'UTF-8')
            && $letters !== mb_strtolower($letters, 'UTF-8');
    }

    private function systemPrompt(string $register, string $lang): string
    {
        $common = sprintf(
            'Du schreibst barrierearme Inhalte in der Sprache "%s". Behalte den Sinn, erfinde nichts dazu. '
            . 'Gib AUSSCHLIESSLICH reinen Fließtext zurück: KEINE HTML-Tags, kein Markdown, '
            . 'keine Aufzählungszeichen, keine Überschriften-Formatierung, keine Links. Nur die reinen Sätze.',
            $lang,
        );

        if ($register === 'leicht') {
            return $common . ' Schreibe in LEICHTER SPRACHE (Regelwapproximation A1): '
                . 'Nur kurze Hauptsätze, ein Gedanke pro Satz, keine Nebensätze. '
                . 'Aktiv statt Passiv. Keine Metaphern, keine Fremd-/Fachwörter — '
                . 'wenn ein schweres Wort nötig ist, erkläre es in einem eigenen kurzen Satz. '
                . 'Große Zahlen und Abkürzungen vermeiden oder erklären.';
        }

        return $common . ' Schreibe in EINFACHER SPRACHE (etwa B1): '
            . 'Kurze, klare Sätze. Aktiv statt Passiv. Wenig Fachsprache; '
            . 'nötige Fachbegriffe kurz erklären. Verschachtelte Sätze auflösen.';
    }

    private function sanitise(string $content): string
    {
        $content = trim($content);

        // Drop markdown code fences a model might wrap around the text.
        $content = preg_replace('/^```[a-z]*\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        // Plain text only — strip any tags the model added anyway, decode entities.
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($content);
    }

    private function modelFor(AiClientInterface $client): string
    {
        $model = trim((string) $this->runtimeConfig->get('ai_model', ''));

        return $model !== '' ? $model : $client->defaultModel();
    }
}
