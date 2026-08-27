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

namespace VTInnovations\AccessPlus\Alt;

use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\Ai\AiClientFactory;
use VTInnovations\AccessPlus\Ai\AiClientInterface;
use VTInnovations\AccessPlus\Ai\PromptBundle;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Turns an image (+ minimal context) into an alt-text proposal via the
 * configured vision model.
 *
 * Doing it RIGHT (the Access+ teardown is the anti-pattern):
 *   - Decorative detection: the model may return decorative=true → empty alt.
 *   - Per language: one call per language, never a single blob.
 *   - Data minimisation: only the image + a short context string leave the
 *     server; never the whole DB (the project guidelines §3.2).
 *   - Egress switch + key handling live in the AI client; this class just asks.
 *   - Output is a PROPOSAL — it is never written live here.
 *
 * The egress kill-switch is enforced inside the client (AiException EgressBlocked
 * propagates to the caller, which surfaces it in the UI).
 */
final class AltTextGenerator
{
    private const MAX_TOKENS = 300;

    public function __construct(
        private readonly AiClientFactory $factory,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(LoadedImage $image, string $lang, string $context): AltProposal
    {
        $client = $this->factory->default();

        $system = sprintf(
            'You are an accessibility expert writing concise HTML alt text in the language "%s". '
            . 'Decide whether the image is purely decorative (adds no information). '
            . 'Respond ONLY with compact JSON: {"decorative": true|false, "alt": "..."}. '
            . 'The alt must be a single sentence under 125 characters, describe the content or '
            . 'function, and must NOT start with "image of" / "Bild von".',
            $lang,
        );

        $user = trim($context) !== ''
            ? 'Context for the image (may help, may be irrelevant): ' . trim($context)
            : 'No additional context is available.';

        $bundle = new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: $this->modelFor($client),
            temperature: 0.2,
            maxTokens: self::MAX_TOKENS,
            purpose: 'alt_text',
            images: [['mime' => $image->mime, 'data' => $image->base64]],
        );

        $response = $client->complete($bundle);
        $json = $response->asJson();

        if (\is_array($json) && \array_key_exists('alt', $json)) {
            return new AltProposal(
                decorative: (bool) ($json['decorative'] ?? false),
                alt: (string) $json['alt'],
            );
        }

        // Model didn't return clean JSON — treat the whole text as the alt and
        // log (without the body) so we can spot a misbehaving model/prompt.
        $this->logger->info('a11y alt generation returned non-JSON; using raw text', [
            'provider' => $response->provider,
            'model' => $response->model,
        ]);

        return new AltProposal(false, trim($response->content));
    }

    private function modelFor(AiClientInterface $client): string
    {
        $model = trim((string) $this->runtimeConfig->get('ai_model', ''));

        return $model !== '' ? $model : $client->defaultModel();
    }
}
