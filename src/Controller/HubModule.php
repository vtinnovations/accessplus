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

use Symfony\Component\HttpFoundation\Request;
use VTInnovations\AccessPlus\Exchange\DeferredSignals;
use VTInnovations\AccessPlus\State\BackendSessionClaim;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * Single backend entry point. Renders one nav item with a tab bar across the
 * top; each tab delegates to the matching feature module. Keeps the left-hand
 * backend navigation compact (one item) instead of a dozen.
 *
 * Multi-domain (Modell 2): a ?root=<id> query param selects the active site root.
 * It is preserved across tab switches and exposed via a domain selector bar (only
 * when more than one root is licensed). Each tab module reads ?root itself and
 * scopes its data.
 *
 * Licensing: a licence belongs to exactly one Startpunkt, so the hub always works
 * on one licensed root. A single licensed root is selected implicitly, several
 * require a choice, and an unlicensed root gets a notice instead of the tools —
 * Contao itself keeps behaving exactly as it would without this bundle.
 */
final class HubModule
{
    use BackendModuleHelper;

    /** @var array<string, array{0: string, 1: class-string}> tab => [language key, module] */
    private const TABS = [
        'dashboard' => ['hub.tab_dashboard', DashboardModule::class],
        'report'    => ['hub.tab_report', ReportModule::class],
        'pdf'       => ['hub.tab_pdf', PdfModule::class],
        'alt'       => ['hub.tab_alt', AltModule::class],
        'aria'      => ['hub.tab_aria', AriaModule::class],
        'subtitle'  => ['hub.tab_subtitle', SubtitleModule::class],
        'simple'    => ['hub.tab_simple', SimplifyModule::class],
        'overlay'   => ['hub.tab_overlay', OverlayModule::class],
        'statement' => ['hub.tab_statement', StatementModule::class],
        'audit'     => ['hub.tab_audit', AuditModule::class],
        'settings'  => ['hub.tab_settings', SettingsModule::class],
    ];

    public function generate(): string
    {
        $request = $this->currentRequest();
        $tab = $request instanceof Request ? (string) $request->query->get('tab', '') : '';
        if (!isset(self::TABS[$tab])) {
            $tab = 'dashboard';
        }

        $root = $request instanceof Request ? (int) $request->query->get('root', 0) : 0;

        // Licence gate. Every tab below operates on the content of one site root,
        // so the hub only ever runs for a root that is licensed right now. This is
        // one of several independent gates — the Ajax endpoints, the CLI commands,
        // the linter and the frontend listeners each check for themselves.
        $gate = $this->resolveLicensedRoot($request, $root);

        if (\is_string($gate)) {
            return $this->wrap($this->tabBar($tab, 0) . $gate);
        }

        $root = $gate;

        $class = self::TABS[$tab][1];
        $module = new $class();
        $inner = $module->generate(); // handles its own POST/PRG; returns wrapped HTML

        return $this->injectTabs($inner, $this->tabBar($tab, $root) . $this->rootBar($tab, $root));
    }

    /**
     * Returns the site root the hub may work on, or ready-made notice HTML when
     * no licensed root can be determined.
     *
     * Install-wide ("Alle Domains") is not a licensable scope: a licence belongs
     * to exactly one Startpunkt, so a single licensed root is selected implicitly
     * and several licensed roots require an explicit choice. The chosen root is
     * written back into the query, which is where every tab reads its scope from.
     *
     * @return int|string
     */
    private function resolveLicensedRoot(?Request $request, int $root)
    {
        $provider = $this->service(SiteStatusProvider::class);
        $licensed = $provider->activeRootIds();

        if ($licensed === []) {
            return $this->licenceNotice(0);
        }

        if ($root <= 0) {
            if (\count($licensed) === 1) {
                $root = $licensed[0];

                // Scope every tab to that root instead of running install-wide.
                $request?->query->set('root', $root);

                $this->signalModuleEntry($root);

                return $root;
            }

            return $this->chooseNotice($licensed);
        }

        if (!\in_array($root, $licensed, true)) {
            return $this->licenceNotice($root);
        }

        $this->signalModuleEntry($root);

        return $root;
    }

    /**
     * Module-entry signal for the licensed root whose tools are actually opened.
     * Same atomic per-session claim the licence section uses, so opening both
     * screens in one session still produces exactly one event per root.
     */
    private function signalModuleEntry(int $root): void
    {
        $status = $this->service(SiteStatusProvider::class)->forRoot($root);

        if (!$status->hasKey() || $status->matchedDomain === null) {
            return;
        }

        if (!$this->service(BackendSessionClaim::class)->claim($root)) {
            return;
        }

        $this->service(DeferredSignals::class)->queueModuleEntry($status->matchedDomain, $status->key());
    }

    private function licenceNotice(int $root): string
    {
        $scope = $this->service(RootScope::class);
        $roots = $scope->roots();

        $html = '<div id="tl_buttons"></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->esc($this->trans('hub.license_required_title')) . '</h1>';
        $html .= '<div class="tl_message"><p class="tl_error">' . $this->esc($this->trans('hub.license_required_body')) . '</p></div>';
        $html .= '<p>' . $this->trans('hub.license_manage_hint', ['link' => $this->esc($this->trans('hub.license_manage_link'))]) . '</p>';

        if ($roots === []) {
            return $html . '<p>' . $this->esc($this->trans('hub.no_root_pages')) . '</p>';
        }

        $html .= '<ul style="margin:12px 0;padding-left:18px;">';
        foreach ($roots as $entry) {
            $url = $this->editUrl('tl_page', $entry['id']);
            $label = $this->esc($scope->label($entry));
            $html .= '<li style="margin:4px 0;">'
                . ($url === null ? $label : '<a href="' . $this->esc($url) . '">' . $label . '</a>')
                . ($root > 0 && $root === $entry['id'] ? ' ' . $this->esc($this->trans('hub.selected_marker')) : '')
                . '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * @param list<int> $licensed
     */
    private function chooseNotice(array $licensed): string
    {
        $scope = $this->service(RootScope::class);

        $html = '<div id="tl_buttons"></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->esc($this->trans('hub.choose_domain_title')) . '</h1>';
        $html .= '<p>' . $this->esc($this->trans('hub.choose_domain_body')) . '</p><ul style="margin:12px 0;padding-left:18px;">';

        foreach ($licensed as $rootId) {
            $root = $scope->root($rootId);

            if ($root === null) {
                continue;
            }

            $html .= '<li style="margin:4px 0;"><a href="contao?do=accessplus&amp;root=' . $rootId . '">'
                . $this->esc($scope->label($root)) . '</a></li>';
        }

        return $html . '</ul>';
    }

    private function tabBar(string $active, int $root): string
    {
        $rootQ = $root > 0 ? '&amp;root=' . $root : '';
        $html = '<nav class="accessplus-tabs" aria-label="' . $this->esc($this->trans('hub.license_required_title')) . '">';
        foreach (self::TABS as $key => [$labelKey]) {
            $cls = $key === $active ? 'accessplus-tab is-active' : 'accessplus-tab';
            $aria = $key === $active ? ' aria-current="page"' : '';
            $html .= '<a class="' . $cls . '" href="contao?do=accessplus&amp;tab=' . $this->esc($key) . $rootQ . '"' . $aria . '>' . $this->esc($this->trans($labelKey)) . '</a>';
        }
        // Reports are a DCA list under their own do — link out.
        $html .= '<a class="accessplus-tab" href="contao?do=accessplus_feedback">' . $this->esc($this->trans('hub.tab_reports_link')) . '</a>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Domain selector (Modell 2). Only shown when more than one site root is
     * licensed — otherwise the scope is unambiguous. A dropdown
     * (not a pill row) so it stays one line even with dozens of domains. Preserves
     * the active tab; only licensed roots are offered.
     *
     * Implemented as a GET form to `contao` (do/tab hidden, root = the select), so
     * choosing an option navigates via a plain query string — no inline JS needed
     * beyond onchange auto-submit (which the Contao backend permits), and it still
     * works if that is blocked via the "Wechseln" button.
     */
    private function rootBar(string $tab, int $activeRoot): string
    {
        $scope = $this->service(RootScope::class);
        $licensed = $this->service(SiteStatusProvider::class)->activeRootIds();

        // Only licensed roots are selectable, and there is no install-wide option:
        // a licence never spans site roots, so neither may the data view.
        $roots = array_values(array_filter(
            $scope->roots(),
            static fn (array $root): bool => \in_array($root['id'], $licensed, true),
        ));

        if (\count($roots) < 2) {
            return '';
        }

        $option = function (int $rootId, string $label, bool $active): string {
            return '<option value="' . $rootId . '"' . ($active ? ' selected' : '') . '>' . $this->esc($label) . '</option>';
        };

        $options = '';
        foreach ($roots as $r) {
            $options .= $option($r['id'], $scope->label($r), $activeRoot === $r['id']);
        }

        $html = '<form class="accessplus-roots" method="get" action="contao">';
        $html .= '<input type="hidden" name="do" value="accessplus">';
        $html .= '<input type="hidden" name="tab" value="' . $this->esc($tab) . '">';
        $html .= '<label class="accessplus-root-label" for="accessplus-root-select">' . $this->esc($this->trans('hub.domain_label')) . '</label>';
        $html .= '<select id="accessplus-root-select" name="root" class="accessplus-root-select tl_select" onchange="this.form.submit()">' . $options . '</select>';
        $html .= '<button type="submit" class="accessplus-root-go">' . $this->esc($this->trans('hub.domain_switch')) . '</button>';
        $html .= '</form>';

        return $html;
    }

    private function injectTabs(string $inner, string $tabBar): string
    {
        $pos = strpos($inner, '<div class="accessplus-be">');
        if ($pos === false) {
            return $tabBar . $inner;
        }

        $insertAt = $pos + \strlen('<div class="accessplus-be">');

        return substr($inner, 0, $insertAt) . $tabBar . substr($inner, $insertAt);
    }
}
