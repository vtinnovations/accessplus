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

use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Message;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use VTInnovations\AccessPlus\I18n\Text;

/**
 * Shared plumbing for the bundle's BE_MOD callback screens.
 *
 * The important bit is the POST-Redirect-GET flow: the Contao 5 backend runs on
 * Turbo, which REQUIRES a POST to answer with a redirect ("Form responses must
 * redirect to another location"). So every state-changing POST sets a
 * Contao\Message (survives the redirect, auto-rendered by the backend) and then
 * redirects back to the same module URL via redirectToSelf(). This also works on
 * 4.13 (Controller::redirect + Message exist there too).
 */
trait BackendModuleHelper
{
    protected function currentRequest(): ?Request
    {
        /** @var RequestStack $stack */
        $stack = System::getContainer()->get('request_stack');

        return $stack->getCurrentRequest();
    }

    /**
     * Selected site root (Modell 2) from the ?root= query param the Hub preserves
     * across tabs. 0 = "Alle Domains" (install-wide).
     */
    protected function currentRoot(): int
    {
        $request = $this->currentRequest();

        return $request instanceof Request ? (int) $request->query->get('root', 0) : 0;
    }

    protected function isTokenValid(Request $request): bool
    {
        $value = (string) $request->request->get('REQUEST_TOKEN', '');

        return $this->csrfTokenManager()->isTokenValid(new CsrfToken($this->csrfTokenName(), $value));
    }

    protected function requestToken(): string
    {
        return $this->csrfTokenManager()->getToken($this->csrfTokenName())->getValue();
    }

    protected function csrfTokenManager(): ContaoCsrfTokenManager
    {
        /** @var ContaoCsrfTokenManager $manager */
        $manager = System::getContainer()->get('contao.csrf.token_manager');

        return $manager;
    }

    protected function csrfTokenName(): string
    {
        return (string) System::getContainer()->getParameter('contao.csrf_token_name');
    }

    /**
     * Deep-link into Contao's native edit mask for the record a finding points
     * at, so "Jetzt fixen" lets the editor change the value (label, link text,
     * page language, heading level) with Contao's own form + validation. Returns
     * null when the finding has no editable DB record (e.g. frontend/CSS issues).
     */
    protected function editUrl(string $ptable, int $pid): ?string
    {
        if ($pid <= 0) {
            return null;
        }

        // tl_files findings are file-centric: the editable record is the file's
        // meta (alt/title/caption), edited via the Files manager (DC_Folder),
        // whose id is the relative PATH — not the numeric tl_files.id. Resolve it.
        if ($ptable === 'tl_files') {
            $path = $this->filePathById($pid);
            if ($path === null) {
                return null;
            }

            return 'contao?do=files&act=edit&id=' . rawurlencode($path)
                . '&rt=' . rawurlencode($this->requestToken());
        }

        $do = match ($ptable) {
            'tl_form_field' => 'form',
            'tl_content' => 'article',
            'tl_page' => 'page',
            default => null,
        };

        if ($do === null) {
            return null;
        }

        return 'contao?do=' . $do . '&table=' . rawurlencode($ptable)
            . '&act=edit&id=' . $pid . '&rt=' . rawurlencode($this->requestToken());
    }

    /**
     * Relative path of a tl_files record by numeric id, or null if gone.
     */
    private function filePathById(int $id): ?string
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        $path = $connection->fetchOne('SELECT path FROM tl_files WHERE id = ?', [$id]);

        return \is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * URL of the "show on page" highlight preview for a finding.
     */
    protected function highlightUrl(int $findingId): string
    {
        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = System::getContainer()->get('router');

        return $router->generate('vtinnovations_accessplus_highlight', ['id' => $findingId]);
    }

    /**
     * Bump when accessplus-backend.css changes (cache-bust). A method, not a const:
     * constants in traits require PHP 8.2, but this bundle's floor is 8.1.
     */
    protected function backendAssetVersion(): string
    {
        return '1.29.1';
    }

    /**
     * Wrap a module screen: load the bundle's backend stylesheet and scope it
     * under .accessplus-be so the modern card/spacing styling applies only to our
     * own screens, never the rest of the Contao backend.
     */
    protected function wrap(string $html): string
    {
        $request = $this->currentRequest();
        $base = $request instanceof Request ? $request->getBasePath() : '';
        $href = $base . '/bundles/vtinnovationsaccessplus/accessplus-backend.css?v=' . $this->backendAssetVersion();

        return '<link rel="stylesheet" href="' . $this->esc($href) . '">'
            . '<div class="accessplus-be">' . $html . '</div>';
    }

    /**
     * Rendered HTML of pending Contao flash messages (confirm/error), and clears
     * them. BE_MOD callback screens must emit this themselves — unlike DCA
     * modules, Contao does not auto-render messages around a callback's output,
     * so without this the messages leak onto the next normal backend page.
     */
    protected function flashMessages(): string
    {
        return Message::generate();
    }

    protected function confirm(string $message): void
    {
        Message::addConfirmation($message);
    }

    protected function error(string $message): void
    {
        Message::addError($message);
    }

    /**
     * PRG redirect back to the current module URL (Turbo-compatible). Exits.
     */
    protected function redirectToSelf(): void
    {
        $request = $this->currentRequest();
        $url = $request instanceof Request ? $request->getRequestUri() : 'contao';

        Controller::redirect($url);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function service(string $id): object
    {
        /** @var T $service */
        $service = System::getContainer()->get($id);

        return $service;
    }

    protected function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, scalar> $params
     */
    protected function trans(string $key, array $params = []): string
    {
        return Text::get($key, $params);
    }
}
