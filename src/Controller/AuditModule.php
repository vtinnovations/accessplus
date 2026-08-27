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
use VTInnovations\AccessPlus\Alt\AltSuggestionService;
use VTInnovations\AccessPlus\Model\AuditModel;

/**
 * Audit trail + Undo (BE_MOD callback). Lists applied fixes and lets an editor
 * revert one — but only if the value has not been changed since (no-clobber,
 * enforced in MetaWriter). POST → PRG redirect (Turbo).
 */
final class AuditModule
{
    use BackendModuleHelper;

    private const CAP = 100;

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

        if ((string) $request->request->get('accessplus_action', '') === 'undo') {
            $ok = $this->service(AltSuggestionService::class)->undo((int) $request->request->get('audit_id', 0));
            $ok
                ? $this->confirm($this->trans('audit.undo_confirm'))
                : $this->error($this->trans('audit.undo_error'));
        }
    }

    private function render(): string
    {
        $token = $this->requestToken();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('audit.title') . '</h1>';

        $entries = $this->entries();
        if ($entries === []) {
            $html .= '<p class="tl_info">' . $this->esc($this->trans('audit.no_entries')) . '</p>';

            return $html;
        }

        $html .= '<table class="tl_listing"><thead><tr>'
            . '<th class="tl_folder_tlist">' . $this->esc($this->trans('audit.col_time')) . '</th><th class="tl_folder_tlist">' . $this->esc($this->trans('audit.col_action')) . '</th>'
            . '<th class="tl_folder_tlist">' . $this->esc($this->trans('audit.col_target')) . '</th><th class="tl_folder_tlist">' . $this->esc($this->trans('audit.col_before_after')) . '</th>'
            . '<th class="tl_folder_tlist">' . $this->esc($this->trans('audit.col_user')) . '</th><th class="tl_folder_tlist">&nbsp;</th>'
            . '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $undone = $entry->undone === '1';
            $before = $entry->beforeAbsent === '1' ? $this->trans('audit.before_absent') : '"' . (string) $entry->beforeValue . '"';
            $after = '"' . (string) $entry->afterValue . '"';

            $html .= '<tr' . ($undone ? ' style="opacity:.5;"' : '') . '>'
                . '<td>' . $this->esc(date('d.m.Y H:i', (int) $entry->tstamp)) . '</td>'
                . '<td>' . $this->esc((string) $entry->action) . '</td>'
                . '<td>' . $this->esc((string) $entry->targetTable . ' · ' . (string) $entry->field . ' [' . (string) $entry->lang . ']') . '</td>'
                . '<td>' . $this->esc($before . '  →  ' . $after) . '</td>'
                . '<td>' . $this->esc((string) $entry->userName) . '</td>'
                . '<td>' . ($undone ? '<em>' . $this->esc($this->trans('audit.undone_label')) . '</em>' : $this->undoButton((int) $entry->id, $token)) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function undoButton(int $auditId, string $token): string
    {
        return '<form method="post" action="" style="margin:0;">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">'
            . '<input type="hidden" name="accessplus_action" value="undo">'
            . '<input type="hidden" name="audit_id" value="' . $auditId . '">'
            . '<button type="submit" class="tl_submit" style="padding:2px 8px;">' . $this->esc($this->trans('audit.undo_btn')) . '</button>'
            . '</form>';
    }

    /**
     * @return list<AuditModel>
     */
    private function entries(): array
    {
        $collection = AuditModel::findBy(['action = ?'], ['alt_apply'], ['order' => 'tstamp DESC', 'limit' => self::CAP]);
        if ($collection === null) {
            return [];
        }

        $out = [];
        foreach ($collection as $model) {
            $out[] = $model;
        }

        return $out;
    }
}
