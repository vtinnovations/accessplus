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

namespace VTInnovations\AccessPlus\FrontendModule;

use Contao\BackendTemplate;
use Contao\Module;
use Contao\System;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\State\SiteStatusProvider;
use VTInnovations\AccessPlus\Statement\StatementService;

/**
 * Frontend module that renders the accessibility statement from the saved config
 * (BFSG/BITV mandatory document). Classic Contao\Module (portable 4.13/5.x);
 * uses an .html5 template so there is no Twig version divergence.
 */
final class StatementFrontendModule extends Module
{
    protected $strTemplate = 'mod_accessplus_statement';

    public function generate(): string
    {
        if ($this->isBackendRequest()) {
            $template = new BackendTemplate('be_wildcard');
            $template->wildcard = Text::get('statement_public.wildcard');

            return $template->parse();
        }

        if (!$this->isLicensedRoot()) {
            return '';
        }

        return parent::generate();
    }

    protected function compile(): void
    {
        /** @var StatementService $service */
        $service = System::getContainer()->get(StatementService::class);

        // Modell 2: the statement is per site root. On the frontend the active root
        // is on the global page object; fall back to install-wide (0) if absent.
        $rootId = isset($GLOBALS['objPage']) ? (int) ($GLOBALS['objPage']->rootId ?? 0) : 0;
        $data = $service->data($rootId);

        $this->Template->statement = $data;
        $this->Template->statusLabel = StatementService::statusLabel((string) $data['status']);
    }

    private function isBackendRequest(): bool
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        return $request !== null
            && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request);
    }

    /**
     * Scope gate. An unlicensed site root must behave as if this bundle were not
     * installed, so the module renders nothing at all there.
     */
    private function isLicensedRoot(): bool
    {
        $rootId = isset($GLOBALS['objPage']) ? (int) ($GLOBALS['objPage']->rootId ?? 0) : 0;

        return System::getContainer()->get(SiteStatusProvider::class)->isActive($rootId);
    }
}
