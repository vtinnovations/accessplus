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
use VTInnovations\AccessPlus\Overlay\OverlayFeatures;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Backend editor for the comfort overlay: master on/off plus a per-feature
 * switch for every widget option, so the operator can ship exactly the set they
 * want. POST + token + PRG.
 *
 * Honesty note: the overlay is a comfort/display layer, never marketed as an
 * accessibility solution (Marketing-Leitplanke).
 */
final class OverlayModule
{
    use BackendModuleHelper;

    public function generate(): string
    {
        $request = $this->currentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            $this->handlePost($request);
            $this->redirectToSelf();
        }

        return $this->wrap($this->render());
    }

    private function handlePost(Request $request): void
    {
        if (!$this->isTokenValid($request)) {
            $this->error($this->trans('common.invalid_token'));

            return;
        }

        $enabledFeatures = [];
        foreach (OverlayFeatures::allIds() as $id) {
            if ($request->request->has('feat_' . $id)) {
                $enabledFeatures[] = $id;
            }
        }

        $color = trim((string) $request->request->get('overlay_btn_color', '#1d4ed8'));
        if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $color) !== 1) {
            $color = '#1d4ed8';
        }

        $position = (string) $request->request->get('overlay_position', 'bottomright');
        $allowed = ['bottomright', 'bottomleft', 'topright', 'topleft', 'middleright', 'middleleft'];
        $position = \in_array($position, $allowed, true) ? $position : 'bottomright';

        $runtimeConfig = $this->service(RuntimeConfig::class);
        $rootId = $this->currentRoot();
        $enabled = $request->request->has('overlay_enabled');

        // Design + feature set stay global (one shared overlay look); only the
        // on/off activation is per site root (Modell 2).
        $runtimeConfig->update([
            'overlay_features' => $enabledFeatures,
            'overlay_btn_color' => $color,
            'overlay_position' => $position,
        ]);

        if ($rootId > 0) {
            $runtimeConfig->updateForRoot($rootId, ['overlay_enabled' => $enabled]);
            $this->confirm($this->trans('overlay.saved_root'));
        } else {
            $runtimeConfig->update(['overlay_enabled' => $enabled]);
            $this->confirm($this->trans('overlay.saved'));
        }
    }

    private function render(): string
    {
        $runtimeConfig = $this->service(RuntimeConfig::class);
        $token = $this->requestToken();
        $rootId = $this->currentRoot();

        $enabledOverlay = (bool) $runtimeConfig->getForRoot($rootId, 'overlay_enabled', false);
        $features = $runtimeConfig->get('overlay_features', OverlayFeatures::allIds());
        $features = \is_array($features) ? $features : OverlayFeatures::allIds();
        $btnColor = (string) $runtimeConfig->get('overlay_btn_color', '#1d4ed8');
        $position = (string) $runtimeConfig->get('overlay_position', 'bottomright');

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('overlay.title') . '</h1>';
        $html .= '<p class="tl_info">' . $this->trans('overlay.disclaimer') . '</p>';

        $html .= '<form method="post" action="">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<div class="tl_formbody_edit">';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('overlay.activation_legend')) . '</legend>';
        if ($rootId > 0) {
            $html .= '<p class="tl_info" style="margin-top:0;">' . $this->trans('overlay.activation_domain_hint') . '</p>';
        }
        $html .= '<div class="widget"><label style="display:flex;gap:8px;align-items:flex-start;">';
        $html .= '<input type="checkbox" name="overlay_enabled" value="1"' . ($enabledOverlay ? ' checked' : '') . '>';
        $html .= '<span><strong>' . $this->esc($this->trans('overlay.activation_toggle')) . '</strong></span></label></div>';
        $html .= '</fieldset>';

        // ── Design ─────────────────────────────────────────────────────
        $positions = [
            'bottomright' => $this->trans('overlay.position_bottomright'), 'bottomleft' => $this->trans('overlay.position_bottomleft'),
            'middleright' => $this->trans('overlay.position_middleright'), 'middleleft' => $this->trans('overlay.position_middleleft'),
            'topright' => $this->trans('overlay.position_topright'), 'topleft' => $this->trans('overlay.position_topleft'),
        ];
        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('overlay.design_legend')) . '</legend>';
        $html .= '<div class="widget" style="margin:4px 0;"><h3><label for="overlay_btn_color">' . $this->esc($this->trans('overlay.color_label')) . '</label></h3>'
            . '<input type="color" name="overlay_btn_color" id="overlay_btn_color" value="' . $this->esc($btnColor) . '"></div>';
        $html .= '<div class="widget" style="margin:4px 0;"><h3><label for="overlay_position">' . $this->esc($this->trans('overlay.position_label')) . '</label></h3>'
            . '<select name="overlay_position" id="overlay_position" class="tl_select">';
        foreach ($positions as $value => $label) {
            $html .= '<option value="' . $this->esc($value) . '"' . ($value === $position ? ' selected' : '') . '>' . $this->esc($label) . '</option>';
        }
        $html .= '</select></div></fieldset>';

        foreach (OverlayFeatures::groups() as [$groupLabel, $featureMap]) {
            $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($groupLabel) . '</legend>';
            foreach ($featureMap as $id => $label) {
                $checked = \in_array($id, $features, true) ? ' checked' : '';
                $html .= '<div class="widget" style="margin:4px 0;"><label style="display:flex;gap:8px;align-items:center;">';
                $html .= '<input type="checkbox" name="feat_' . $this->esc($id) . '" value="1"' . $checked . '>';
                $html .= '<span>' . $this->esc($label) . '</span></label></div>';
            }
            $html .= '</fieldset>';
        }

        $html .= '</div>';
        $html .= '<div class="tl_formbody_submit"><div class="tl_submit_container">';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('common.save')) . '</button>';
        $html .= '</div></div></form>';

        return $html;
    }
}
