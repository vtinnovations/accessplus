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

namespace VTInnovations\AccessPlus\Ai\Provider;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTInnovations\AccessPlus\Ai\AiClientInterface;
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\Ai\AiExceptionKind;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Security\SecretStore;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Shared plumbing for HTTP-based providers: the egress kill-switch, the
 * SecretStore-backed key lookup, and base-URL hardening. Concrete clients
 * implement only the request/response shaping.
 */
abstract class AbstractHttpClient implements AiClientInterface
{
    protected const SECRET_NAME = 'ai_api_key';

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly RuntimeConfig $runtimeConfig,
        protected readonly SecretStore $secretStore,
    ) {
    }

    /**
     * Enforce the "no external calls" mode BEFORE any network work. Defence in
     * depth — also enforced one layer up, but a client must never be the leak.
     *
     * @throws AiException
     */
    protected function assertEgressAllowed(): void
    {
        if ($this->runtimeConfig->externalCallsBlocked()) {
            throw new AiException(
                Text::get('ai.egress_blocked_error'),
                AiExceptionKind::EgressBlocked,
            );
        }
    }

    /**
     * @throws AiException
     */
    protected function requireApiKey(): string
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

    /**
     * Validates an admin-configured base URL: http/https only, with a host.
     * Defends against accidental file://, gopher://, etc. (SSRF surface,
     * the project guidelines §3.4). Returns the trimmed base, or the provider default when
     * empty.
     *
     * @throws AiException
     */
    protected function resolveBaseUrl(string $default): string
    {
        $configured = trim((string) $this->runtimeConfig->get('ai_base_url', ''));
        if ($configured === '') {
            return $default;
        }

        $scheme = strtolower((string) parse_url($configured, PHP_URL_SCHEME));
        $host = (string) parse_url($configured, PHP_URL_HOST);

        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new AiException(
                Text::get('ai.invalid_base_url_error'),
                AiExceptionKind::BadRequest,
            );
        }

        return rtrim($configured, '/');
    }
}
