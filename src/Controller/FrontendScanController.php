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
use VTInnovations\AccessPlus\State\UsageLedger;

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
 *
 * Licensing — this endpoint IS the other two of the three permitted Demo
 * operations (page scanning + axe results), enforced at exactly this
 * server-side boundary so no other controller/export/API path can reach a
 * scanned page's findings without having gone through it:
 *   - Pro (Yearly/Lifetime) roots: unchanged, unlimited behaviour.
 *   - Demo roots: each non-finalize ingest call represents one chargeable page
 *     scan and first reserves one unit of the cumulative 15-page allowance;
 *     once spent, further pages for that root are rejected outright (the axe
 *     run's result for that page is simply never stored). While a page scan is
 *     still permitted, THAT page's own findings are additionally trimmed to
 *     the first 30 issues before they are ever persisted — so issue 31+ of a
 *     single page's result never exists in the store for any later endpoint,
 *     export or template to expose. This 30-issue cap is a per-result cap
 *     (spec section 1.C), not a cumulative allowance: it is not tracked in
 *     {@see UsageLedger} and is not among the persisted "images/pages
 *     consumed" counters the spec mandates — only a fresh page's own result
 *     is ever truncated, so a later page can still show its own first 30.
 */
final class FrontendScanController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly AxeResultMapper $mapper,
        private readonly FrontendFindingStore $store,
        private readonly SiteStatusProvider $siteStatus,
        private readonly UsageLedger $usageLedger,
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
        $rootId = (int) ($payload['root'] ?? 0);

        if (!$this->siteStatus->isActive($rootId)) {
            return new JsonResponse(['ok' => false, 'error' => 'not_licensed'], 403);
        }

        $demo = !$this->siteStatus->isFullyLicensed($rootId);

        // Finalize: resolve not-seen findings ONLY when the scan fully covered all
        // pages (the client reports coverage). Partial coverage = no auto-resolve.
        if (!empty($payload['finalize'])) {
            $fullCoverage = !empty($payload['cover']);
            // Scope auto-resolve to the scanned root so a per-domain scan never
            // resolves another domain's frontend findings (Modell 2). Finalize
            // itself is bookkeeping, not a scan of a page — it never consumes
            // Demo allowance.
            $resolved = $this->store->finalizeScan($scanStart, $fullCoverage, $rootId);

            return new JsonResponse(['ok' => true, 'resolved' => $resolved]);
        }

        // Demo: this POST is one chargeable page scan. Reserve one unit of the
        // cumulative page allowance before doing any work for it; once the
        // allowance is spent, the page is rejected outright and nothing about
        // it is stored. The reservation is atomic (UsageLedger locks per root),
        // so two concurrent scans of the same Demo root cannot together exceed
        // the limit.
        if ($demo && !$this->usageLedger->tryConsume($rootId, 'pages_scanned', SiteStatusProvider::DEMO_PAGE_SCAN_LIMIT)) {
            return new JsonResponse(['ok' => false, 'error' => 'demo_quota_exceeded'], 403);
        }

        $pageId = (int) ($payload['pageId'] ?? 0);
        $title = (string) ($payload['title'] ?? '');
        $url = (string) ($payload['url'] ?? '');
        $violations = \is_array($payload['violations'] ?? null) ? $payload['violations'] : [];

        $findings = $this->mapper->map($pageId, $title, array_values($violations));

        // Demo: truncate THIS page's own result to its first 30 issues BEFORE
        // anything is persisted. This is the sole server-side truncation
        // boundary — issue 31+ of this result is never written, so no later
        // read path (dashboard, export, another endpoint) can expose it, and
        // no client-side count can be trusted or tampered with. Deliberately
        // per-result, not cumulative across pages/scans (see class docblock).
        if ($demo && \count($findings) > SiteStatusProvider::DEMO_AXE_ISSUE_LIMIT) {
            $findings = \array_slice($findings, 0, SiteStatusProvider::DEMO_AXE_ISSUE_LIMIT);
        }

        $this->store->ingestPage($scanStart, $findings, $url, $pageId);

        return new JsonResponse(['ok' => true, 'found' => \count($findings)]);
    }
}
