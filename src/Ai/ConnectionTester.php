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

namespace VTInnovations\AccessPlus\Ai;

use Psr\Log\LoggerInterface;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Manual "is my AI config working?" probe for the settings screen. Sends a
 * single, minimal prompt to the configured provider and reports a UI-safe
 * verdict.
 *
 * Guard rails (the project guidelines §3.2):
 *   - Honours the egress kill-switch: with external calls blocked it returns a
 *     clear, non-failing message and never hits the network.
 *   - Data minimisation: a one-word probe, no site content.
 *   - Never logs or returns the key or the raw response body.
 *
 * Phase 1 scope: this is the ONLY production code path allowed to call out, and
 * only on explicit admin action (POST + token).
 */
final class ConnectionTester
{
    public function __construct(
        private readonly AiClientFactory $factory,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function test(): TestResult
    {
        if ($this->runtimeConfig->externalCallsBlocked()) {
            return TestResult::failure(Text::get('ai.test_not_run'));
        }

        try {
            $client = $this->factory->default();
        } catch (\RuntimeException $e) {
            return TestResult::failure(Text::get('ai.config_error_prefix', ['message' => $e->getMessage()]));
        }

        $bundle = new PromptBundle(
            systemPrompt: 'You are a connection test. Reply with the single word: OK.',
            userPrompt: 'ping',
            model: $this->modelFor($client),
            temperature: 0.0,
            maxTokens: 8,
            purpose: 'connection_test',
        );

        try {
            $response = $client->complete($bundle);
        } catch (AiException $e) {
            // Log kind only, never the key/body (the message is already scrubbed).
            $this->logger->warning('a11y connection test failed', ['kind' => $e->kind->value]);

            return TestResult::failure(Text::get('ai.connection_failed_prefix', ['message' => $e->getMessage()]));
        }

        return TestResult::success(Text::get('ai.connection_ok', [
            'provider' => $response->provider,
            'model' => $response->model,
            'ms' => $response->durationMs,
        ]));
    }

    private function modelFor(AiClientInterface $client): string
    {
        $model = trim((string) $this->runtimeConfig->get('ai_model', ''));

        return $model !== '' ? $model : $client->defaultModel();
    }
}
