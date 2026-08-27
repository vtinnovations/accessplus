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

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use VTInnovations\AccessPlus\Frontend\AxeResultMapper;
use VTInnovations\AccessPlus\Frontend\FrontendFindingStore;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Ingest endpoint for the browser-side axe scanner. The BE iframe runs axe on a
 * frontend page and POSTs the violations here; we map them to findings
 * (sourceType=frontend) and reconcile that page.
 *
 * Security:
 *   - Route lives under the backend route prefix → behind the backend firewall
 *     (authenticated backend users only).
 *   - Request token validated explicitly (no mutating GET; POST + token).
 *   - The axe payload is untrusted: every value is cast/escaped downstream and
 *     stored as plaintext, never executed or echoed raw.
 */
final class FrontendScanController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly AxeResultMapper $mapper,
        private readonly FrontendFindingStore $store,
        private readonly SiteStatusProvider $siteStatus,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly string $csrfTokenName,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $token = (string) ($request->headers->get('X-Contao-Request-Token')
            ?? $request->request->get('REQUEST_TOKEN', ''));
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_token'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['ok' => false, 'error' => 'bad_payload'], 400);
        }

        $scanStart = (int) ($payload['scanStart'] ?? 0);
        if ($scanStart <= 0) {
            return new JsonResponse(['ok' => false, 'error' => 'bad_scan'], 400);
        }

        // Scope gate: ingest writes findings for the scanned site root, so that
        // root must be licensed. An unlicensed root's data is never touched.
        if (!$this->siteStatus->isActive((int) ($payload['root'] ?? 0))) {
            return new JsonResponse(['ok' => false, 'error' => 'not_licensed'], 403);
        }

        // Finalize: resolve not-seen findings ONLY when the scan fully covered all
        // pages (the client reports coverage). Partial coverage = no auto-resolve.
        if (!empty($payload['finalize'])) {
            $fullCoverage = !empty($payload['cover']);
            // Scope auto-resolve to the scanned root so a per-domain scan never
            // resolves another domain's frontend findings (Modell 2).
            $rootId = (int) ($payload['root'] ?? 0);
            $resolved = $this->store->finalizeScan($scanStart, $fullCoverage, $rootId);

            return new JsonResponse(['ok' => true, 'resolved' => $resolved]);
        }

        $pageId = (int) ($payload['pageId'] ?? 0);
        $title = (string) ($payload['title'] ?? '');
        $url = (string) ($payload['url'] ?? '');
        $violations = \is_array($payload['violations'] ?? null) ? $payload['violations'] : [];

        $findings = $this->mapper->map($pageId, $title, array_values($violations));
        $this->store->ingestPage($scanStart, $findings, $url, $pageId);

        return new JsonResponse(['ok' => true, 'found' => \count($findings)]);
    }
}
