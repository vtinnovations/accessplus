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
use VTInnovations\AccessPlus\Model\AriaFixModel;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Receives the "elements missing an accessible name" candidates the frontend scan
 * derives from the live DOM (selector + a heuristic name + a short HTML snippet)
 * and upserts them as pending aria-label fixes. Editor decisions (approved or
 * rejected) are never clobbered by a re-scan.
 *
 * Security: backend firewall + explicit request-token check; no mutating GET;
 * every stored value is untrusted text, escaped on output and applied only via
 * setAttribute at runtime.
 */
final class AriaIngestController
{
    private const CAP = 300;

    /** Only these axe rules map to a missing accessible name. */
    private const NAME_RULES = [
        'link-name', 'button-name', 'frame-title', 'aria-input-field-name',
        'input-button-name', 'aria-command-name', 'aria-toggle-field-name', 'input-image-alt',
    ];

    public function __construct(
        private readonly ContaoFramework $framework,
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

        // Scope gate: ARIA fixes are applied to the pages of one site root.
        if (!$this->siteStatus->isActive(\is_array($payload) ? (int) ($payload['root'] ?? 0) : 0)) {
            return new JsonResponse(['ok' => false, 'error' => 'not_licensed'], 403);
        }

        $candidates = \is_array($payload) && \is_array($payload['candidates'] ?? null) ? $payload['candidates'] : [];

        $now = time();
        $stored = 0;

        foreach ($candidates as $c) {
            if ($stored >= self::CAP || !\is_array($c)) {
                break;
            }

            $selector = trim((string) ($c['selector'] ?? ''));
            $ruleId = (string) ($c['ruleId'] ?? '');
            if ($selector === '' || !\in_array($ruleId, self::NAME_RULES, true)) {
                continue;
            }

            $fingerprint = sha1($selector);
            $model = AriaFixModel::findOneByFingerprint($fingerprint);

            // Never overwrite a decision the editor already made.
            if ($model !== null && \in_array($model->status, ['approved', 'rejected'], true)) {
                continue;
            }

            if ($model === null) {
                $model = new AriaFixModel();
                $model->createdAt = $now;
                $model->status = 'pending';
                $model->fingerprint = $fingerprint;
                $model->attribute = 'aria-label';
            }

            $model->tstamp = $now;
            $model->selector = mb_substr($selector, 0, 512);
            $model->ruleId = mb_substr($ruleId, 0, 64);
            $model->pageUrl = mb_substr((string) ($c['url'] ?? ''), 0, 2048);
            $model->html = mb_substr((string) ($c['html'] ?? ''), 0, 1000);
            $model->suggestion = mb_substr(trim((string) ($c['suggestion'] ?? '')), 0, 255);
            $model->save();
            ++$stored;
        }

        return new JsonResponse(['ok' => true, 'stored' => $stored]);
    }
}
