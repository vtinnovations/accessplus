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

namespace VTInnovations\AccessPlus\Subtitle;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\Ai\AiExceptionKind;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Security\SecretStore;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Transcribes a local audio/video file to WebVTT via the OpenAI (or compatible)
 * Whisper endpoint: POST {base}/audio/transcriptions with response_format=vtt.
 *
 * Guarantees (the project guidelines §3):
 *   - Egress kill-switch checked BEFORE any network work.
 *   - Key from SecretStore (same 'ai_api_key' as the chat clients), never config.
 *   - Only http/https base, openai/compatible provider (Whisper is OpenAI-shaped).
 *   - File stays local except the single upload; nothing else leaves the server.
 *   - No response body / key echoed into logs or exceptions.
 *
 * Whisper hard limit is 25 MB per file — enforced locally to fail fast with a
 * helpful message instead of a 413 from the provider.
 */
final class AudioTranscriber
{
    public const SECRET_NAME = 'ai_api_key';

    private const DEFAULT_BASE = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'whisper-1';
    private const MAX_BYTES = 25 * 1024 * 1024;
    private const TIMEOUT_SECS = 600;

    /** Providers whose API is Whisper-compatible. */
    private const SUPPORTED_PROVIDERS = ['openai', 'compatible'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RuntimeConfig $runtimeConfig,
        private readonly SecretStore $secretStore,
        private readonly string $projectDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $relativePath Path under the project root (e.g. files/video/clip.mp4)
     * @param string $lang         ISO-639-1 hint for the spoken language
     *
     * @throws AiException        Egress/auth/provider/network problems
     * @throws MediaLoadException Local file problems (missing, too big)
     */
    public function transcribe(string $relativePath, string $lang): TranscriptResult
    {
        $this->assertEgressAllowed();
        $this->assertProviderSupported();

        $apiKey = $this->requireApiKey();
        $absolute = $this->resolveReadableFile($relativePath);

        $model = trim((string) $this->runtimeConfig->get('subtitle_model', '')) ?: self::DEFAULT_MODEL;
        $endpoint = $this->resolveBaseUrl() . '/audio/transcriptions';

        $fields = [
            'model' => $model,
            'response_format' => 'vtt',
            'file' => DataPart::fromPath($absolute),
        ];

        // Language hint improves accuracy; only send a plausible 2-letter code.
        $hint = strtolower(substr(trim($lang), 0, 2));
        if (preg_match('/^[a-z]{2}$/', $hint) === 1) {
            $fields['language'] = $hint;
        }

        $formData = new FormDataPart($fields);
        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: Bearer ' . $apiKey;

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'timeout' => self::TIMEOUT_SECS,
                'max_redirects' => 0,
                'headers' => $headers,
                'body' => $formData->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportException $e) {
            throw new AiException(
                'Netzwerkfehler zur Transkriptions-API: ' . $e->getMessage(),
                AiExceptionKind::Transport,
                previous: $e,
            );
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if ($statusCode !== 200) {
            $this->throwForErrorStatus($statusCode, $body);
        }

        $vtt = $this->normaliseVtt($body);
        if ($vtt === '') {
            throw new AiException('Transkription lieferte leeren Inhalt.', AiExceptionKind::InvalidResponse);
        }

        $this->logger->info('a11y subtitle transcription ok', [
            'provider' => $this->provider(),
            'model' => $model,
            'durationMs' => $durationMs,
        ]);

        return new TranscriptResult($vtt, $this->provider(), $model, $durationMs);
    }

    private function assertEgressAllowed(): void
    {
        if ($this->runtimeConfig->externalCallsBlocked()) {
            throw new AiException(
                'Externe Aufrufe sind deaktiviert ("Keine externen Aufrufe"). Bitte erst in den Einstellungen freigeben.',
                AiExceptionKind::EgressBlocked,
            );
        }
    }

    private function assertProviderSupported(): void
    {
        if (!\in_array($this->provider(), self::SUPPORTED_PROVIDERS, true)) {
            throw new AiException(
                Text::get('ai.whisper_unsupported_provider'),
                AiExceptionKind::BadRequest,
            );
        }
    }

    private function provider(): string
    {
        return (string) $this->runtimeConfig->get('ai_provider', 'openai');
    }

    private function requireApiKey(): string
    {
        $key = $this->secretStore->get(self::SECRET_NAME);
        if ($key === null || $key === '') {
            throw new AiException(
                Text::get('ai.no_api_key_error'),
                AiExceptionKind::Auth,
            );
        }

        return $key;
    }

    private function resolveReadableFile(string $relativePath): string
    {
        // No traversal: must stay inside the project's files/ tree.
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new MediaLoadException(Text::get('subtitle.invalid_file_path_error'));
        }

        $absolute = $this->projectDir . '/' . $relativePath;
        if (!is_file($absolute) || !is_readable($absolute)) {
            throw new MediaLoadException(Text::get('subtitle.media_file_unreadable_error', ['file' => basename($relativePath)]));
        }

        $size = filesize($absolute);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new MediaLoadException(Text::get('subtitle.file_too_large_for_transcription_error', [
                'size' => (int) round((int) $size / 1024 / 1024),
            ]));
        }

        return $absolute;
    }

    private function resolveBaseUrl(): string
    {
        $configured = trim((string) $this->runtimeConfig->get('ai_base_url', ''));
        if ($configured === '') {
            return self::DEFAULT_BASE;
        }

        $scheme = strtolower((string) parse_url($configured, PHP_URL_SCHEME));
        $host = (string) parse_url($configured, PHP_URL_HOST);
        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new AiException(Text::get('ai.invalid_base_url_error'), AiExceptionKind::BadRequest);
        }

        return rtrim($configured, '/');
    }

    /**
     * Ensure a clean WebVTT: must start with the WEBVTT signature; CRLF→LF.
     */
    private function normaliseVtt(string $body): string
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
        if ($body === '') {
            return '';
        }

        if (!str_starts_with($body, 'WEBVTT')) {
            $body = "WEBVTT\n\n" . $body;
        }

        return $body;
    }

    private function throwForErrorStatus(int $statusCode, string $body): never
    {
        $decoded = json_decode($body, true);
        $errorMsg = \is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
        $errorCode = \is_array($decoded) ? (string) ($decoded['error']['code'] ?? '') : '';

        $kind = match (true) {
            $statusCode === 401 || $statusCode === 403 => AiExceptionKind::Auth,
            $statusCode === 429 => AiExceptionKind::RateLimit,
            $statusCode >= 400 && $statusCode < 500 => AiExceptionKind::BadRequest,
            $statusCode >= 500 => AiExceptionKind::ServerError,
            default => AiExceptionKind::Unknown,
        };

        if ($errorCode === 'insufficient_quota') {
            $kind = AiExceptionKind::QuotaExceeded;
        }

        // Status + provider error type only — never the raw body.
        throw new AiException(
            sprintf('Transkriptions-API %d%s', $statusCode, $errorMsg !== '' ? ': ' . $errorMsg : ''),
            $kind,
            providerErrorCode: $errorCode !== '' ? $errorCode : null,
        );
    }
}
