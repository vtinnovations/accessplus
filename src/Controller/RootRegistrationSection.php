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

use Contao\BackendUser;
use Contao\DataContainer;
use Symfony\Component\HttpFoundation\Request;
use VTInnovations\AccessPlus\Exchange\DeferredSignals;
use VTInnovations\AccessPlus\Security\PackageRejected;
use VTInnovations\AccessPlus\State\BackendSessionClaim;
use VTInnovations\AccessPlus\State\RootScope;
use VTInnovations\AccessPlus\State\SiteRegistrar;
use VTInnovations\AccessPlus\State\SiteState;
use VTInnovations\AccessPlus\State\SiteStatus;
use VTInnovations\AccessPlus\State\SiteStatusProvider;

/**
 * The ONE administrator surface for this bundle's registration, rendered inside
 * the Contao root-page settings (Seitenstruktur → Startpunkt bearbeiten), in its
 * own section directly above the access-protection section. There is no second
 * screen, no settings tab, no standalone module and no Ajax route for it.
 *
 * Wiring, end to end, without a single line of JavaScript:
 *
 *   rendered button (submit inside Contao's own edit form)
 *     -> POST contao?do=page&act=edit&id=<rootId>
 *     -> Contao request-token check (+ explicit re-check below)
 *     -> backend user permission (DC_Table page rights + explicit re-check below)
 *     -> tl_page config.onsubmit_callback -> onSubmit()
 *     -> SiteRegistrar (exchange, verification, atomic store)
 *     -> Contao\Message + Contao's own reload (POST-Redirect-GET)
 *     -> render() reads the authoritative store again and shows the new state
 *
 * Instantiated by Contao via `new` (like the bundle's other backend screens), so
 * it takes no constructor arguments and pulls services from the container.
 */
final class RootRegistrationSection
{
    use BackendModuleHelper;

    private const ACTION_FIELD = 'accessplus_registration_action';

    private const KEY_FIELD = 'accessplus_registration_key';

    private const CONFIRM_FIELD = 'accessplus_registration_confirm';

    /**
     * Renders the section. Called by Contao as an input_field_callback.
     */
    public function render(DataContainer $dc, string $xlabel = ''): string
    {
        $rootId = (int) ($dc->id ?? 0);
        $scope = $this->service(RootScope::class);

        // Only site roots have a registration; ordinary pages never see this.
        if ($rootId <= 0 || $scope->root($rootId) === null) {
            return '';
        }

        $provider = $this->service(SiteStatusProvider::class);
        $status = $provider->forRoot($rootId);

        $this->signalModuleEntry($rootId, $status);

        $html = '<div class="widget accessplus-registration" style="margin-bottom:14px;">';
        $html .= $this->stateBlock($status);
        $html .= $this->controlBlock($status);
        $html .= '</div>';

        return $html;
    }

    /**
     * Executes the submitted action. Called by Contao as a config.onsubmit_callback
     * for tl_page, i.e. inside Contao's own authenticated, token-checked backend
     * request.
     */
    public function onSubmit(DataContainer $dc): void
    {
        $request = $this->currentRequest();

        if (!$request instanceof Request || !$request->isMethod('POST')) {
            return;
        }

        $action = (string) $request->request->get(self::ACTION_FIELD, '');

        if (!\in_array($action, ['activate', 'refresh', 'remove'], true)) {
            return; // an ordinary page save
        }

        // Re-check the request token even though Contao already validated it for
        // this backend route: a state-changing licence operation should not
        // depend on another layer staying configured the way it is today.
        if (!$this->isTokenValid($request)) {
            $this->error($this->trans('common.invalid_token'));

            return;
        }

        if (!$this->mayManage()) {
            $this->error($this->trans('license.no_permission'));

            return;
        }

        $rootId = (int) ($dc->id ?? 0);
        $scope = $this->service(RootScope::class);

        if ($rootId <= 0 || $scope->root($rootId) === null) {
            $this->error($this->trans('license.not_a_root'));

            return;
        }

        $registrar = $this->service(SiteRegistrar::class);
        $key = (string) $request->request->get(self::KEY_FIELD, '');

        try {
            if ($action === 'remove') {
                if (!$request->request->has(self::CONFIRM_FIELD)) {
                    $this->error($this->trans('license.remove_not_confirmed'));

                    return;
                }

                $registrar->remove($rootId);
                $this->confirm($this->trans('license.removed_confirm'));

                return;
            }

            $status = $action === 'activate'
                ? $registrar->activate($rootId, $key)
                : $registrar->refresh($rootId, $key);

            if ($status->isActive()) {
                $this->confirm($this->trans('license.activated_confirm'));
            } else {
                $this->error($this->trans('license.accepted_but_invalid', ['reason' => $this->reasonText($status->reason)]));
            }
        } catch (PackageRejected $e) {
            $this->error($this->reasonText($e->category()));
        } catch (\Throwable) {
            $this->error($this->trans('license.check_failed'));
        }
    }

    /**
     * Once per authenticated backend session and site root, when this section is
     * actually opened and an authentic record exists. The claim is made BEFORE
     * the (deferred) delivery, so a timeout cannot cause a second attempt in the
     * same session.
     */
    private function signalModuleEntry(int $rootId, SiteStatus $status): void
    {
        if (!$status->hasKey() || $status->matchedDomain === null) {
            return;
        }

        if (!$this->service(BackendSessionClaim::class)->claim($rootId)) {
            return;
        }

        $this->service(DeferredSignals::class)->queueModuleEntry($status->matchedDomain, $status->key());
    }

    /**
     * The state, then the same five facts every V-T.ONE licence surface shows
     * and no more: which licence (masked — the full key is never rendered),
     * which package, since when, until when, last verified when.
     *
     * The licensed domain, the signed domain set, the allowance and the
     * document version were dropped: they are record internals nobody acts on
     * from this screen. The site root's own domains stay, but only while the
     * licence is not active — there they are the fix, not trivia.
     */
    private function stateBlock(SiteStatus $status): string
    {
        $rows = [
            $this->trans('license.state_label') => $this->stateText($status),
        ];

        if (!$status->isActive()) {
            $rows[$this->trans('license.domains_label')] = $status->configuredDomains === []
                ? $this->trans('license.no_domain_configured')
                : implode(', ', $status->configuredDomains);
        }

        if ($status->state === SiteState::Active || $status->state === SiteState::Expired) {
            $rows[$this->trans('license.masked_key_label')] = $status->maskedKey();
            $rows[$this->trans('license.package_label')] = strtoupper($status->package);
            $rows[$this->trans('license.valid_from_label')] = $this->date($status->startsAt);
            $rows[$this->trans('license.valid_until_label')] = $status->lifetime ? $this->trans('license.lifetime_label') : $this->date($status->expiresAt);
            $rows[$this->trans('license.last_checked_label')] = $this->date($status->verifiedAt);
        }

        $html = '<table class="tl_show" style="margin-bottom:10px;">';

        foreach ($rows as $label => $value) {
            $html .= '<tr><td class="tl_label" style="padding-right:12px;"><strong>' . $this->esc($label) . '</strong></td>'
                . '<td>' . $this->esc($value) . '</td></tr>';
        }

        return $html . '</table>';
    }

    private function controlBlock(SiteStatus $status): string
    {
        $html = '';

        if ($status->configuredDomains === []) {
            $html .= '<p class="tl_help" style="color:#c33;">' . $this->esc($this->trans('license.no_domain_warning')) . '</p>';
        }

        $html .= '<h3><label for="' . self::KEY_FIELD . '">' . $this->esc($this->trans('license.key_label'))
            . ($status->hasKey() ? $this->esc($this->trans('license.key_stored_suffix')) : '')
            . '</label></h3>';
        $html .= '<input type="password" name="' . self::KEY_FIELD . '" id="' . self::KEY_FIELD . '" class="tl_text"'
            . ' value="" autocomplete="off" spellcheck="false" placeholder="'
            . $this->esc($status->hasKey() ? $this->trans('license.key_placeholder_kept') : $this->trans('license.key_placeholder_enter')) . '">';
        $html .= '<p class="tl_help">' . $this->esc($this->trans('license.key_help')) . '</p>';

        $html .= '<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        $html .= $this->button('activate', $this->trans('license.activate_btn'));

        if ($status->hasKey()) {
            $html .= $this->button('refresh', $this->trans('license.refresh_btn'));
            $html .= $this->button('remove', $this->trans('license.remove_btn'));
            $html .= '<label style="display:inline-flex;gap:6px;align-items:center;">'
                . '<input type="checkbox" name="' . self::CONFIRM_FIELD . '" value="1"> ' . $this->esc($this->trans('license.confirm_remove_label')) . '</label>';
        }

        $html .= '</div>';

        return $html;
    }

    private function button(string $action, string $label): string
    {
        return '<button type="submit" class="tl_submit" name="' . self::ACTION_FIELD . '" value="' . $this->esc($action) . '">'
            . $this->esc($label) . '</button>';
    }

    private function stateText(SiteStatus $status): string
    {
        return match ($status->state) {
            SiteState::Active => $this->trans('license.state_active'),
            SiteState::Expired => $this->trans('license.state_expired'),
            SiteState::Invalid => $this->trans('license.state_invalid', ['reason' => $this->reasonText($status->reason)]),
            SiteState::Unlicensed => $this->trans('license.state_unlicensed'),
        };
    }

    /**
     * Internal categories → generic administrator text. No protocol detail, no
     * hint about which cryptographic step failed.
     */
    private function reasonText(string $category): string
    {
        $key = match ($category) {
            'key_missing' => 'license.reason_key_missing',
            'no_configured_domain' => 'license.reason_no_configured_domain',
            'domain_not_configured', 'domain_binding_invalid', 'domain_mismatch' => 'license.reason_domain_mismatch',
            'package_not_permitted' => 'license.reason_package_not_permitted',
            'expired' => 'license.reason_expired',
            'not_yet_valid' => 'license.reason_not_yet_valid',
            'refresh_required' => 'license.reason_refresh_required',
            'version_rejected' => 'license.reason_version_rejected',
            'transport_failed', 'service_unavailable' => 'license.reason_service_unavailable',
            'service_denied' => 'license.reason_service_denied',
            'signature_runtime_unavailable' => 'license.reason_signature_runtime_unavailable',
            'scope_invalid' => 'license.reason_scope_invalid',
            default => 'license.reason_default',
        };

        return $this->trans($key);
    }

    private function date(?int $timestamp): string
    {
        return $timestamp === null || $timestamp <= 0 ? '—' : date('d.m.Y H:i', $timestamp);
    }

    /**
     * Defence in depth on top of Contao's own page-edit rights: the field is an
     * excluded field, so a non-admin additionally needs it granted explicitly.
     */
    private function mayManage(): bool
    {
        try {
            $user = BackendUser::getInstance();
        } catch (\Throwable) {
            return false;
        }

        if ($user->isAdmin) {
            return true;
        }

        return $user->hasAccess('page', 'modules')
            && $user->hasAccess('tl_page::accessplus_registration', 'alexf');
    }
}
