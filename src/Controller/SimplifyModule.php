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
use VTInnovations\AccessPlus\Simplify\SimplifyItem;
use VTInnovations\AccessPlus\Simplify\SimplifyService;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Backend screen for plain/easy-language drafts (BE_MOD callback). Configure the
 * feature, pick a page + register, generate drafts (opt-in, POST + token + PRG,
 * respects the egress switch), then review original vs. draft per element and
 * approve/reject. Approved drafts are swapped into the frontend in place; nothing
 * is published automatically.
 */
final class SimplifyModule
{
    use BackendModuleHelper;

    private const REGISTER_LABEL_KEYS = ['einfach' => 'simple.register_einfach', 'leicht' => 'simple.register_leicht'];

    public function generate(): string
    {
        $request = $this->currentRequest();
        if ($request instanceof Request && $request->isMethod('POST')) {
            $this->handlePost($request);
            $this->redirectToSelf();
        }

        return $this->wrap($this->render($request));
    }

    private function handlePost(Request $request): void
    {
        if (!$this->isTokenValid($request)) {
            $this->error($this->trans('common.invalid_token'));

            return;
        }

        $service = $this->service(SimplifyService::class);
        $config = $this->service(RuntimeConfig::class);
        $action = (string) $request->request->get('accessplus_action', '');

        switch ($action) {
            case 'savesettings':
                $registers = (array) $request->request->all('registers');
                $registers = array_values(array_intersect(SimplifyService::REGISTERS, $registers));
                $enabled = $request->request->get('simple_enabled') === '1';
                // Registers + placement stay global; only the on/off activation is
                // per site root (Modell 2).
                $config->update([
                    'simple_registers'      => $registers !== [] ? $registers : SimplifyService::REGISTERS,
                    'simple_switch_overlay' => $request->request->get('simple_switch_overlay') === '1',
                    'simple_switch_button'  => $request->request->get('simple_switch_button') === '1',
                    'simple_switch_nav'     => $request->request->get('simple_switch_nav') === '1',
                ]);
                if ($this->currentRoot() > 0) {
                    $config->updateForRoot($this->currentRoot(), ['simple_enabled' => $enabled]);
                    $this->confirm($this->trans('simple.settings_saved_root'));
                } else {
                    $config->update(['simple_enabled' => $enabled]);
                    $this->confirm($this->trans('simple.settings_saved'));
                }
                break;

            case 'generate':
                $summary = $service->generateForPage(
                    (int) $request->request->get('page', 0),
                    (string) $request->request->get('register', 'einfach'),
                    (string) $request->request->get('lang', 'de'),
                );
                $summary->blocked || $summary->errors > 0
                    ? $this->error($summary->message)
                    : $this->confirm($summary->message);
                break;

            case 'savedraft':
                $service->saveDraft((int) $request->request->get('sid', 0), (string) $request->request->get('draft', ''));
                $this->confirm($this->trans('simple.savedraft_confirm'));
                break;

            case 'approve':
                $service->approve((int) $request->request->get('sid', 0));
                $this->confirm($this->trans('simple.approve_confirm'));
                break;

            case 'reject':
                $service->reject((int) $request->request->get('sid', 0));
                $this->confirm($this->trans('simple.reject_confirm'));
                break;

            case 'lock':
                $service->lock((int) $request->request->get('sid', 0));
                $this->confirm($this->trans('simple.lock_confirm'));
                break;

            case 'unlock':
                $service->unlock((int) $request->request->get('sid', 0));
                $this->confirm($this->trans('simple.unlock_confirm'));
                break;

            case 'approveall':
                $n = $service->approveAll(
                    (int) $request->request->get('page', 0),
                    (string) $request->request->get('register', 'einfach'),
                    (string) $request->request->get('lang', 'de'),
                );
                $this->confirm($this->trans('simple.approveall_confirm', ['count' => $n]));
                break;
        }
    }

    private function render(?Request $request): string
    {
        $token = $this->requestToken();
        $config = $this->service(RuntimeConfig::class);
        $service = $this->service(SimplifyService::class);
        $blocked = $config->externalCallsBlocked();

        $sp = (string) ($request?->query->get('sp', '') ?? '');
        $scopeAll = $sp === 'all';
        $selectedPage = $scopeAll ? 0 : (int) $sp;
        $register = (string) ($request?->query->get('sr', 'einfach') ?? 'einfach');
        $register = \in_array($register, SimplifyService::REGISTERS, true) ? $register : 'einfach';
        $lang = (string) ($request?->query->get('sl', 'de') ?? 'de');

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('simple.title') . '</h1>';
        $html .= '<p class="tl_info">' . $this->trans('simple.disclaimer') . '</p>';

        $html .= $this->renderSettings($config, $token);

        // Page + register selector (GET → query params).
        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('simple.page_select_legend')) . '</legend>';
        $html .= '<form method="get" action="" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        foreach (['do' => 'accessplus', 'tab' => 'simple'] as $k => $val) {
            $html .= '<input type="hidden" name="' . $k . '" value="' . $this->esc($val) . '">';
        }
        $html .= '<label>' . $this->esc($this->trans('simple.scope_label')) . ' <select name="sp" class="tl_select">';
        $html .= '<option value="">' . $this->esc($this->trans('simple.page_placeholder')) . '</option>';
        $html .= '<option value="all"' . ($scopeAll ? ' selected' : '') . '>' . $this->esc($this->trans('simple.page_all_option')) . '</option>';
        foreach ($service->listPages() as $page) {
            $sel = $page['id'] === $selectedPage ? ' selected' : '';
            $defLang = $lang;
            if ($page['id'] === $selectedPage && $page['language'] !== '') {
                $defLang = $page['language'];
            }
            $html .= '<option value="' . $page['id'] . '"' . $sel . '>' . $this->esc($page['title']) . ' [' . $this->esc($page['alias']) . ']</option>';
        }
        $html .= '</select></label> ';
        $html .= '<label>' . $this->esc($this->trans('simple.register_label')) . ' <select name="sr" class="tl_select">';
        foreach (SimplifyService::REGISTERS as $r) {
            $sel = $r === $register ? ' selected' : '';
            $html .= '<option value="' . $r . '"' . $sel . '>' . $this->esc($this->trans(self::REGISTER_LABEL_KEYS[$r])) . '</option>';
        }
        $html .= '</select></label> ';
        $html .= '<label>' . $this->esc($this->trans('simple.lang_label')) . ' <input type="text" name="sl" value="' . $this->esc($lang) . '" class="tl_text" style="width:60px;"></label> ';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('simple.load_btn')) . '</button>';
        $html .= '</form></fieldset>';

        if (!$scopeAll && $selectedPage <= 0) {
            return $html;
        }

        $items = $service->itemsForPage($selectedPage, $register, $lang);
        if ($items === []) {
            $html .= '<p class="tl_info">' . $this->esc($this->trans('simple.no_items')) . '</p>';

            return $html;
        }

        if ($scopeAll) {
            $html .= '<p class="tl_info">' . $this->trans('simple.scope_all_hint') . '</p>';
        }

        // Generate + approve-all bar.
        $html .= '<form method="post" action="" style="margin:12px 0;display:flex;gap:8px;">';
        $html .= $this->hidden($token, $selectedPage, $register, $lang);
        $html .= '<button type="submit" name="accessplus_action" value="generate" class="tl_submit"' . ($blocked ? ' disabled' : '') . '>' . $this->esc($this->trans('simple.generate_btn', ['count' => \count($items)])) . '</button>';
        $html .= '<button type="submit" name="accessplus_action" value="approveall" class="tl_submit">' . $this->esc($this->trans('simple.approveall_btn')) . '</button>';
        $html .= '</form>';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans(self::REGISTER_LABEL_KEYS[$register])) . ' (' . \count($items) . ')</legend>';
        foreach ($items as $item) {
            $html .= $this->renderItem($item, $token, $selectedPage, $register, $lang);
        }
        $html .= '</fieldset>';

        return $html;
    }

    private function renderSettings(RuntimeConfig $config, string $token): string
    {
        $enabled = (bool) $config->getForRoot($this->currentRoot(), 'simple_enabled', false);
        $registers = (array) $config->get('simple_registers', SimplifyService::REGISTERS);

        $html = '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('simple.settings_legend')) . '</legend>';
        $html .= '<form method="post" action="">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="savesettings">';
        $html .= '<p><label><input type="checkbox" name="simple_enabled" value="1"' . ($enabled ? ' checked' : '') . '> ' . $this->esc($this->trans('simple.settings_enabled_label')) . '</label></p>';
        $html .= '<p>' . $this->esc($this->trans('simple.settings_registers_label'));
        foreach (self::REGISTER_LABEL_KEYS as $r => $labelKey) {
            $checked = \in_array($r, $registers, true) ? ' checked' : '';
            $html .= ' <label style="margin-right:12px;"><input type="checkbox" name="registers[]" value="' . $r . '"' . $checked . '> ' . $this->esc($this->trans($labelKey)) . '</label>';
        }
        $html .= '</p>';
        $html .= '<p>' . $this->esc($this->trans('simple.settings_switch_label'));
        $html .= ' <label style="margin-right:12px;"><input type="checkbox" name="simple_switch_overlay" value="1"' . ((bool) $config->get('simple_switch_overlay', true) ? ' checked' : '') . '> ' . $this->esc($this->trans('simple.settings_switch_overlay')) . '</label>';
        $html .= ' <label style="margin-right:12px;"><input type="checkbox" name="simple_switch_button" value="1"' . ((bool) $config->get('simple_switch_button', true) ? ' checked' : '') . '> ' . $this->esc($this->trans('simple.settings_switch_button')) . '</label>';
        $html .= ' <label><input type="checkbox" name="simple_switch_nav" value="1"' . ((bool) $config->get('simple_switch_nav', false) ? ' checked' : '') . '> ' . $this->esc($this->trans('simple.settings_switch_nav')) . '</label>';
        $html .= '</p>';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('simple.settings_save_btn')) . '</button>';
        $html .= '</form></fieldset>';

        return $html;
    }

    private function renderItem(SimplifyItem $item, string $token, int $page, string $register, string $lang): string
    {
        $draft = $item->draft;
        $status = $draft !== null ? (string) $draft->status : 'none';

        $locked = $draft !== null && $draft->locked === '1';

        $badge = match ($status) {
            'approved' => '<span style="color:#3a7;font-weight:bold;">' . $this->esc($this->trans('simple.status_approved')) . '</span>',
            'rejected' => '<span style="color:#a33;">' . $this->esc($this->trans('simple.status_rejected')) . '</span>',
            'pending' => '<span style="color:#c80;font-weight:bold;">' . $this->esc($this->trans('simple.status_pending')) . '</span>',
            default => '<span style="color:#888;">' . $this->esc($this->trans('simple.status_none')) . '</span>',
        };
        if ($locked) {
            $badge .= ' &nbsp; <span style="color:#06c;font-weight:bold;">' . $this->esc($this->trans('simple.locked_badge')) . '</span>';
        }

        $html = '<div style="padding:10px 0;border-bottom:1px solid rgba(128,128,128,.2);' . ($locked ? 'background:rgba(0,102,204,.05);' : '') . '">';
        $html .= '<strong>' . $this->esc($this->trans('simple.element_label', ['id' => $item->contentId])) . '</strong> <span style="color:#888;">[' . $this->esc($item->type) . ']</span> &nbsp; ' . $badge;
        $html .= '<div style="display:flex;gap:14px;margin-top:6px;flex-wrap:wrap;">';
        $html .= '<div style="flex:1;min-width:260px;"><em>' . $this->esc($this->trans('simple.original_label')) . '</em><div style="background:rgba(128,128,128,.06);padding:8px;border-radius:4px;max-height:160px;overflow:auto;">'
            . strip_tags($item->originalHtml, '<p><br><ul><ol><li><strong><em><h2><h3>') . '</div></div>';

        if ($draft !== null) {
            $html .= '<div style="flex:1;min-width:260px;"><em>' . $this->esc($this->trans('simple.draft_label')) . '</em>';
            $html .= '<form method="post" action="" style="margin:0;">';
            $html .= $this->hidden($token, $page, $register, $lang);
            $html .= '<input type="hidden" name="sid" value="' . (int) $draft->id . '">';
            $html .= '<textarea name="draft" rows="6" class="tl_textarea" style="width:100%;"' . ($locked ? ' readonly' : '') . '>' . $this->esc(trim(html_entity_decode(strip_tags((string) $draft->draft), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) . '</textarea>';
            $html .= '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">';
            if ($locked) {
                $html .= '<button type="submit" name="accessplus_action" value="unlock" class="tl_submit">' . $this->esc($this->trans('simple.unlock_btn')) . '</button>';
            } else {
                $html .= '<button type="submit" name="accessplus_action" value="savedraft" class="tl_submit">' . $this->esc($this->trans('simple.savedraft_btn')) . '</button>';
                $html .= '<button type="submit" name="accessplus_action" value="approve" class="tl_submit">' . $this->esc($this->trans('simple.approve_btn')) . '</button>';
                $html .= '<button type="submit" name="accessplus_action" value="lock" class="tl_submit" title="' . $this->esc($this->trans('simple.lock_btn_title')) . '">' . $this->esc($this->trans('simple.lock_btn')) . '</button>';
                $html .= '<button type="submit" name="accessplus_action" value="reject" class="tl_submit">' . $this->esc($this->trans('common.discard_btn')) . '</button>';
            }
            $html .= '</div></form></div>';
        } else {
            $html .= '<div style="flex:1;min-width:260px;color:#888;">' . $this->esc($this->trans('simple.no_draft_hint')) . '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    private function hidden(string $token, int $page, string $register, string $lang): string
    {
        return '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">'
            . '<input type="hidden" name="page" value="' . $page . '">'
            . '<input type="hidden" name="register" value="' . $this->esc($register) . '">'
            . '<input type="hidden" name="lang" value="' . $this->esc($lang) . '">';
    }
}
