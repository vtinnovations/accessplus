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
use VTInnovations\AccessPlus\Ai\ConnectionTester;
use VTInnovations\AccessPlus\Security\SecretStore;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Backend settings screen, wired as a classic BE_MOD callback in
 * contao/config/config.php. Identical on Contao 4.13 and 5.x; renders plain
 * backend HTML (no Twig theme/asset package to misconfigure).
 *
 * Security (the project guidelines §3.3):
 *   - All writes are POST + Contao request token, then a PRG redirect (Turbo).
 *   - The API key is write-only in the UI: we show "gesetzt"/"leer", never the
 *     value, and offer an explicit "remove key" action.
 *   - Every echoed value is escaped.
 */
final class SettingsModule
{
    use BackendModuleHelper;

    private const SECRET_NAME = 'ai_api_key';

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

        $action = (string) $request->request->get('accessplus_action', '');

        if ($action === 'test') {
            $result = $this->service(ConnectionTester::class)->test();
            $result->ok ? $this->confirm($result->message) : $this->error($result->message);

            return;
        }

        if ($action !== 'save') {
            return;
        }

        $runtimeConfig = $this->service(RuntimeConfig::class);
        $secretStore = $this->service(SecretStore::class);

        $provider = (string) $request->request->get('ai_provider', 'openai');
        $provider = \in_array($provider, ['openai', 'compatible'], true) ? $provider : 'openai';

        $wcag = (string) $request->request->get('wcag_target', 'AA');
        $wcag = \in_array($wcag, ['A', 'AA', 'AAA'], true) ? $wcag : 'AA';

        $interval = (int) $request->request->get('monitor_interval', 120);
        $interval = max(30, min(86400, $interval));

        $runtimeConfig->update([
            'ai_provider' => $provider,
            'ai_base_url' => trim((string) $request->request->get('ai_base_url', '')),
            'ai_model' => trim((string) $request->request->get('ai_model', '')),
            'wcag_target' => $wcag,
            'languages' => $this->parseLanguages((string) $request->request->get('languages', '')),
            // Unchecked checkboxes are absent → default to the SAFE state (blocked).
            'no_external_calls' => $request->request->has('no_external_calls'),
            'monitor_on_save' => $request->request->has('monitor_on_save'),
            'monitor_interval' => $interval,
        ]);

        if ($request->request->has('clear_key')) {
            $secretStore->delete(self::SECRET_NAME);
            $this->confirm($this->trans('settings.key_cleared_confirm'));
        } else {
            $newKey = (string) $request->request->get('ai_api_key', '');
            if ($newKey !== '') {
                $secretStore->set(self::SECRET_NAME, $newKey);
                $this->confirm($this->trans('settings.key_saved_confirm'));
            }
        }

        $this->confirm($this->trans('settings.saved_confirm'));
    }

    /**
     * @return list<string>
     */
    private function parseLanguages(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw) ?: [] as $code) {
            $code = strtolower(trim($code));
            if ($code !== '' && preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $code) === 1) {
                $out[$code] = $code;
            }
        }

        return array_values($out) ?: ['de'];
    }

    private function render(): string
    {
        $runtimeConfig = $this->service(RuntimeConfig::class);
        $secretStore = $this->service(SecretStore::class);

        $token = $this->requestToken();
        $provider = (string) $runtimeConfig->get('ai_provider', 'openai');
        $wcag = (string) $runtimeConfig->get('wcag_target', 'AA');
        $baseUrl = (string) $runtimeConfig->get('ai_base_url', '');
        $model = (string) $runtimeConfig->get('ai_model', '');
        $languages = $runtimeConfig->get('languages', ['de']);
        $languagesStr = \is_array($languages) ? implode(', ', array_map('strval', $languages)) : 'de';
        $noExternal = $runtimeConfig->externalCallsBlocked();
        $monitorOnSave = (bool) $runtimeConfig->get('monitor_on_save', true);
        $monitorInterval = (int) $runtimeConfig->get('monitor_interval', 120);
        $keySet = $secretStore->has(self::SECRET_NAME);

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('settings.title') . '</h1>';

        $html .= '<form method="post" action="">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="save">';
        $html .= '<div class="tl_formbody_edit">';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('settings.provider_legend')) . '</legend>';
        $html .= $this->widget('ai_provider', $this->trans('settings.provider_label'), $this->select('ai_provider', [
            'openai' => $this->trans('settings.provider_openai'),
            'compatible' => $this->trans('settings.provider_compatible'),
        ], $provider));
        $html .= $this->widget('ai_base_url', $this->trans('settings.base_url_label'),
            '<input type="url" name="ai_base_url" id="ai_base_url" class="tl_text" value="' . $this->esc($baseUrl) . '" placeholder="https://api.openai.com/v1">');
        $html .= $this->widget('ai_model', $this->trans('settings.model_label'),
            '<input type="text" name="ai_model" id="ai_model" class="tl_text" value="' . $this->esc($model) . '">');

        $keyState = $keySet
            ? '<span style="color:#6cab00;font-weight:bold;">' . $this->esc($this->trans('settings.api_key_state_set')) . '</span>'
            : '<span style="color:#c33;font-weight:bold;">' . $this->esc($this->trans('settings.api_key_state_empty')) . '</span>';
        $html .= $this->widget('ai_api_key', $this->trans('settings.api_key_label', ['state' => $keyState]),
            '<input type="password" name="ai_api_key" id="ai_api_key" class="tl_text" value="" autocomplete="new-password" placeholder="' . $this->esc($keySet ? $this->trans('settings.api_key_placeholder_kept') : $this->trans('settings.api_key_placeholder_enter')) . '">'
            . '<p class="tl_help">' . $this->esc($this->trans('settings.api_key_help')) . '</p>'
            . ($keySet ? '<label style="display:block;margin-top:6px;"><input type="checkbox" name="clear_key" value="1"> ' . $this->esc($this->trans('settings.clear_key_label')) . '</label>' : ''));
        $html .= '</fieldset>';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('settings.privacy_legend')) . '</legend>';
        $html .= '<div class="widget">';
        $html .= '<label style="display:flex;gap:8px;align-items:flex-start;">';
        $html .= '<input type="checkbox" name="no_external_calls" value="1"' . ($noExternal ? ' checked' : '') . '>';
        $html .= '<span><strong>' . $this->esc($this->trans('settings.no_external_calls_label')) . '</strong>' . $this->trans('settings.no_external_calls_desc') . '</span>';
        $html .= '</label>';
        $html .= '<p class="tl_help">' . $this->trans('settings.no_external_calls_help') . '</p>';
        $html .= '</div></fieldset>';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('settings.accessibility_legend')) . '</legend>';
        $html .= $this->widget('wcag_target', $this->trans('settings.wcag_target_label'), $this->select('wcag_target', [
            'A' => 'A',
            'AA' => $this->trans('settings.wcag_aa_recommended'),
            'AAA' => 'AAA',
        ], $wcag));
        $html .= $this->widget('languages', $this->trans('settings.languages_label'),
            '<input type="text" name="languages" id="languages" class="tl_text" value="' . $this->esc($languagesStr) . '">');
        $html .= '</fieldset>';

        // ── Monitoring ─────────────────────────────────────────────────
        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('settings.monitor_legend')) . '</legend>';
        $html .= '<div class="widget">';
        $html .= '<label style="display:flex;gap:8px;align-items:flex-start;">';
        $html .= '<input type="checkbox" name="monitor_on_save" value="1"' . ($monitorOnSave ? ' checked' : '') . '>';
        $html .= '<span><strong>' . $this->esc($this->trans('settings.monitor_on_save_label')) . '</strong>' . $this->esc($this->trans('settings.monitor_on_save_desc')) . '</span>';
        $html .= '</label></div>';
        $html .= $this->widget('monitor_interval', $this->trans('settings.monitor_interval_label'),
            '<input type="number" name="monitor_interval" id="monitor_interval" class="tl_text" min="30" max="86400" value="' . $monitorInterval . '">'
            . '<p class="tl_help">' . $this->esc($this->trans('settings.monitor_interval_help')) . '</p>');
        $html .= '</fieldset>';

        $html .= '</div>';
        $html .= '<div class="tl_formbody_submit"><div class="tl_submit_container">';
        $html .= '<button type="submit" name="save" class="tl_submit">' . $this->esc($this->trans('common.save')) . '</button>';
        $html .= '</div></div>';
        $html .= '</form>';

        $html .= '<form method="post" action="" style="margin-top:18px;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="test">';
        $html .= '<div class="tl_formbody_submit"><div class="tl_submit_container">';
        $html .= '<button type="submit" class="tl_submit"' . ($noExternal ? ' disabled title="' . $this->esc($this->trans('common.egress_blocked_title')) . '"' : '') . '>' . $this->esc($this->trans('settings.test_btn')) . '</button>';
        $html .= '</div></div>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, array $options, string $current): string
    {
        $html = '<select name="' . $this->esc($name) . '" id="' . $this->esc($name) . '" class="tl_select">';
        foreach ($options as $value => $label) {
            $selected = $value === $current ? ' selected' : '';
            $html .= '<option value="' . $this->esc($value) . '"' . $selected . '>' . $this->esc($label) . '</option>';
        }

        return $html . '</select>';
    }

    private function widget(string $for, string $label, string $field): string
    {
        return '<div class="widget" style="margin-bottom:14px;">'
            . '<h3><label for="' . $this->esc($for) . '">' . $label . '</label></h3>'
            . $field
            . '</div>';
    }
}
