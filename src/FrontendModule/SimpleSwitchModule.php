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
use VTInnovations\AccessPlus\Simplify\SimplifyService;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Frontend module rendering plain/easy-language switch links. The links carry
 * data-accessplus-register; the shared accessplus-simple.js (injected when the feature is
 * enabled) enhances them to set the cookie and reload. No inline script.
 * Classic Contao\Module + .html5 template (portable 4.13/5.x).
 */
final class SimpleSwitchModule extends Module
{
    protected $strTemplate = 'mod_accessplus_simple_switch';

    private const LABEL_KEYS = ['' => 'simple.register_none', 'einfach' => 'simple.register_einfach', 'leicht' => 'simple.register_leicht'];

    public function generate(): string
    {
        if ($this->isBackendRequest()) {
            $template = new BackendTemplate('be_wildcard');
            $template->wildcard = Text::get('simple_switch.wildcard');

            return $template->parse();
        }

        if (!$this->isLicensedRoot()) {
            return '';
        }

        /** @var RuntimeConfig $config */
        $config = System::getContainer()->get(RuntimeConfig::class);
        if (!(bool) $config->get('simple_enabled', false)) {
            return '';
        }

        return parent::generate();
    }

    protected function compile(): void
    {
        /** @var RuntimeConfig $config */
        $config = System::getContainer()->get(RuntimeConfig::class);

        $registers = $config->get('simple_registers', SimplifyService::REGISTERS);
        if (!\is_array($registers) || $registers === []) {
            $registers = SimplifyService::REGISTERS;
        }
        $registers = array_values(array_intersect(SimplifyService::REGISTERS, $registers));

        $options = [];
        foreach (array_merge([''], $registers) as $reg) {
            $options[] = ['register' => $reg, 'label' => isset(self::LABEL_KEYS[$reg]) ? Text::get(self::LABEL_KEYS[$reg]) : $reg];
        }

        $this->Template->options = $options;
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
