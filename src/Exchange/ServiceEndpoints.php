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

namespace VTInnovations\AccessPlus\Exchange;

/**
 * Product identity and the fixed destinations this bundle is allowed to talk to.
 *
 * Both destinations are compile-time constants assembled from fragments. They can
 * NOT be influenced by configuration, database content, request data, a remote
 * response or an environment variable — there is no setter and no parameter.
 * That is the whole point: a licence check that can be redirected is not a
 * licence check.
 *
 * The identity triple must match the catalogue entry on the service side; a
 * client change alone does not create server support for it.
 */
final class ServiceEndpoints
{
    /** Display/stable product name used in packets. */
    public const PROJECT = 'AccessPlus';

    /** Route-safe identifier used in packets and in the callback path. */
    public const PROJECT_SLUG = 'accessplus';

    /** Catalogue identifier on the service side. */
    public const PRODUCT_ID = 'vt-accessplus';

    /** Exact application path the service posts updates to. */
    public const CALLBACK_PATH = '/rest/api/v1/accessplus-license-updater';

    /** Only this scheme+host may ever be contacted. */
    private const HOST_FRAGMENTS = ['eno.t-v', 'www'];

    private const VERIFY_FRAGMENTS = ['yfirev', '1v', 'ipa'];

    private const SIGNAL_FRAGMENTS = ['ekovne-gol', '1v', 'ipa', 'tser'];

    /**
     * Activation/refresh destination.
     */
    public function verify(): string
    {
        return $this->base() . '/' . $this->path(self::VERIFY_FRAGMENTS);
    }

    /**
     * Invocation/module-entry signal destination.
     */
    public function signal(): string
    {
        return $this->base() . '/' . $this->path(self::SIGNAL_FRAGMENTS);
    }

    /**
     * Guards against a mistyped fragment ever producing an off-profile target:
     * https only, exact host, no userinfo, no port, no query.
     */
    public function isOwnDestination(string $url): bool
    {
        $parts = parse_url($url);

        if (!\is_array($parts)) {
            return false;
        }

        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== $this->host()) {
            return false;
        }

        // Each part is checked on its own: isset() with several arguments is an
        // AND, so a single combined call would only reject a URL that carried
        // every one of these at once — i.e. it would let a bare port through.
        foreach (['port', 'user', 'pass', 'query', 'fragment'] as $part) {
            if (isset($parts[$part])) {
                return false;
            }
        }

        return true;
    }

    public function host(): string
    {
        $out = [];
        foreach (self::HOST_FRAGMENTS as $fragment) {
            array_unshift($out, strrev($fragment));
        }

        return implode('.', $out);
    }

    private function base(): string
    {
        return 'https://' . $this->host();
    }

    /**
     * @param list<string> $fragments
     */
    private function path(array $fragments): string
    {
        $out = [];
        foreach ($fragments as $fragment) {
            array_unshift($out, strrev($fragment));
        }

        return implode('/', $out);
    }
}
