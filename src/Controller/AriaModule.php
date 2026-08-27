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
use VTInnovations\AccessPlus\Aria\NameRecommender;
use VTInnovations\AccessPlus\Model\AriaFixModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Backend tab "ARIA-Namen": review and apply accessible names for elements the
 * frontend scan reported as unnamed (link-name, button-name, frame-title,
 * aria-input-field-name). The editor edits a prefilled name and approves it;
 * AriaInjector then sets it at runtime (never overriding an existing name).
 */
final class AriaModule
{
    use BackendModuleHelper;

    /** axe name-rule => language key for its short label. */
    private const RULE_LABELS = [
        'link-name'             => 'aria.rule_link_name',
        'button-name'           => 'aria.rule_button_name',
        'frame-title'           => 'aria.rule_frame_title',
        'aria-input-field-name' => 'aria.rule_input_field_name',
        'input-button-name'     => 'aria.rule_input_button_name',
        'aria-command-name'     => 'aria.rule_command_name',
        'aria-toggle-field-name' => 'aria.rule_toggle_field_name',
        'input-image-alt'       => 'aria.rule_image_alt',
    ];

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

        $action = (string) $request->request->get('aria_action', '');
        $id = (int) $request->request->get('id', 0);
        $fix = $id > 0 ? AriaFixModel::findById($id) : null;

        if ($fix === null) {
            return;
        }

        if ($action === 'approve') {
            $value = trim((string) $request->request->get('value', ''));
            if ($value === '') {
                $value = trim((string) $fix->suggestion);
            }
            if ($value === '') {
                $this->error($this->trans('aria.name_required_error'));

                return;
            }
            $fix->value = mb_substr($value, 0, 255);
            $fix->status = 'approved';
            $fix->tstamp = time();
            $fix->save();
            $this->confirm($this->trans('aria.applied_confirm', ['name' => $fix->value]));

            return;
        }

        if ($action === 'reject') {
            $fix->status = 'rejected';
            $fix->tstamp = time();
            $fix->save();
            $this->confirm($this->trans('common.suggestion_discarded'));

            return;
        }

        if ($action === 'reopen') {
            $fix->status = 'pending';
            $fix->tstamp = time();
            $fix->save();

            return;
        }

        if ($action === 'improve') {
            if ($this->service(RuntimeConfig::class)->externalCallsBlocked()) {
                $this->error($this->trans('aria.ai_blocked_error'));

                return;
            }
            $name = $this->service(NameRecommender::class)->improve((string) $fix->html);
            if ($name === '') {
                $this->error($this->trans('aria.ai_no_suggestion_error'));

                return;
            }
            $fix->value = mb_substr($name, 0, 255);
            $fix->tstamp = time();
            $fix->save();
            $this->confirm($this->trans('aria.ai_confirm', ['name' => $fix->value]));

            return;
        }
    }

    private function render(): string
    {
        $token = $this->requestToken();
        $noExternal = $this->service(RuntimeConfig::class)->externalCallsBlocked();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('aria.title') . '</h1>';
        $html .= '<p class="tl_info">' . $this->trans('aria.intro') . '</p>';

        $pending = AriaFixModel::findByStatus('pending', ['order' => 'tstamp DESC']);
        $approved = AriaFixModel::findByStatus('approved', ['order' => 'tstamp DESC']);

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('aria.open_legend'))
            . ($pending !== null ? ' (' . $pending->count() . ')' : '') . '</legend>';
        if ($pending === null) {
            $html .= '<p>' . $this->esc($this->trans('aria.open_empty')) . '</p>';
        } else {
            foreach ($pending as $fix) {
                $html .= $this->renderRow($fix, $token, $noExternal, true);
            }
        }
        $html .= '</fieldset>';

        if ($approved !== null) {
            $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('aria.active_legend')) . ' (' . $approved->count() . ')</legend>';
            foreach ($approved as $fix) {
                $html .= $this->renderRow($fix, $token, $noExternal, false);
            }
            $html .= '</fieldset>';
        }

        return $html;
    }

    private function renderRow(AriaFixModel $fix, string $token, bool $noExternal, bool $editable): string
    {
        $ruleLabel = isset(self::RULE_LABELS[$fix->ruleId]) ? $this->trans(self::RULE_LABELS[$fix->ruleId]) : (string) $fix->ruleId;
        $prefill = (string) $fix->value !== '' ? (string) $fix->value : (string) $fix->suggestion;

        $h = '<div class="widget" style="border:1px solid rgba(127,127,127,.25);border-radius:6px;padding:12px;margin-bottom:12px;">';
        $h .= '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;opacity:.7;">' . $this->esc($ruleLabel) . '</div>';

        // Element preview (escaped HTML snippet).
        if ((string) $fix->html !== '') {
            $h .= '<pre style="white-space:pre-wrap;word-break:break-all;background:rgba(127,127,127,.08);padding:6px 8px;border-radius:4px;margin:6px 0;font-size:12px;">'
                . $this->esc(mb_substr((string) $fix->html, 0, 300)) . '</pre>';
        }
        $h .= '<div style="font-size:12px;opacity:.7;word-break:break-all;">' . $this->esc($this->trans('aria.selector_label')) . ' <code>' . $this->esc((string) $fix->selector) . '</code></div>';

        if ((string) $fix->pageUrl !== '') {
            $h .= '<div style="margin:4px 0;"><a href="' . $this->esc((string) $fix->pageUrl) . '" target="_blank" rel="noopener">' . $this->esc($this->trans('common.show_on_page')) . '</a></div>';
        }

        $h .= '<form method="post" action="" style="margin-top:8px;">';
        $h .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $h .= '<input type="hidden" name="id" value="' . (int) $fix->id . '">';
        $h .= '<input type="text" name="value" class="tl_text" style="max-width:520px;" value="' . $this->esc($prefill) . '" placeholder="' . $this->esc($this->trans('aria.name_placeholder')) . '">';
        $h .= '<div class="tl_submit_container" style="margin-top:8px;">';

        if ($editable) {
            $h .= '<button type="submit" name="aria_action" value="approve" class="tl_submit">' . $this->esc($this->trans('common.apply_btn')) . '</button> ';
            $h .= '<button type="submit" name="aria_action" value="improve" class="tl_submit"'
                . ($noExternal ? ' disabled title="' . $this->esc($this->trans('common.egress_blocked_title')) . '"' : '') . '>' . $this->esc($this->trans('aria.ai_suggest_btn')) . '</button> ';
            $h .= '<button type="submit" name="aria_action" value="reject" class="tl_submit">' . $this->esc($this->trans('common.discard_btn')) . '</button>';
        } else {
            $h .= '<button type="submit" name="aria_action" value="approve" class="tl_submit">' . $this->esc($this->trans('common.save')) . '</button> ';
            $h .= '<button type="submit" name="aria_action" value="reopen" class="tl_submit">' . $this->esc($this->trans('aria.deactivate_btn')) . '</button>';
        }

        $h .= '</div></form></div>';

        return $h;
    }
}
