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
use VTInnovations\AccessPlus\Dashboard\FullAnalysis;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Runs the database (server-side) part of the combined full scan and returns the
 * run summary as JSON, so the dashboard's client orchestrator can show progress
 * and then proceed to the frontend axe scan.
 */
final class DbAnalyzeController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly FullAnalysis $fullAnalysis,
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

        // Scope gate. The analysis writes findings for one site root, so it runs
        // only for a root this installation is licensed for right now.
        $payload = json_decode((string) $request->getContent(), true);
        $rootId = \is_array($payload) ? (int) ($payload['root'] ?? 0) : 0;

        if (!$this->siteStatus->isActive($rootId)) {
            return new JsonResponse(['ok' => false, 'error' => 'not_licensed'], 403);
        }

        $run = $this->fullAnalysis->run($rootId);

        return new JsonResponse([
            'ok' => true,
            'score' => (int) $run->score,
            'oneClick' => (int) $run->countOneClick,
            'manual' => (int) $run->countManual,
            'done' => (int) $run->countDone,
        ]);
    }
}
