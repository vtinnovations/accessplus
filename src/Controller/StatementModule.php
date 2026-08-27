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
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\Statement\StatementService;

/**
 * Backend editor for the accessibility statement (BFSG/BITV mandatory document)
 * + feedback recipient. Saves to the JSON config; the statement is rendered by
 * the frontend module. POST + token + PRG.
 *
 * Carries a "keine Rechtsberatung" notice — the tool helps assemble the document
 * but the operator is responsible for its legal correctness (Marketing-Leitplanke).
 */
final class StatementModule
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

        $status = (string) $request->request->get('statement_status', 'partial');
        $status = \in_array($status, ['conformant', 'partial', 'nonconformant'], true) ? $status : 'partial';

        $method = (string) $request->request->get('statement_method', 'self');
        $method = \in_array($method, ['self', 'external'], true) ? $method : 'self';

        $recipient = trim((string) $request->request->get('feedback_recipient', ''));
        if ($recipient !== '' && !filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
            $recipient = '';
            $this->error($this->trans('statement.invalid_recipient_error'));
        }

        $data = [
            'statement_org' => trim((string) $request->request->get('statement_org', '')),
            'statement_url' => trim((string) $request->request->get('statement_url', '')),
            'statement_status' => $status,
            'statement_nonaccessible' => trim((string) $request->request->get('statement_nonaccessible', '')),
            'statement_contact_name' => trim((string) $request->request->get('statement_contact_name', '')),
            'statement_contact_email' => trim((string) $request->request->get('statement_contact_email', '')),
            'statement_contact_phone' => trim((string) $request->request->get('statement_contact_phone', '')),
            'statement_prepared' => trim((string) $request->request->get('statement_prepared', '')),
            'statement_method' => $method,
            'statement_enforcement' => trim((string) $request->request->get('statement_enforcement', '')),
            'feedback_recipient' => $recipient,
        ];

        // A statement is a per-website legal document (BFSG). On a multi-domain
        // install each root gets its own; root 0 = the install-wide default.
        $rootId = $this->currentRoot();
        if ($rootId > 0) {
            $this->service(RuntimeConfig::class)->updateForRoot($rootId, $data);
            $this->confirm($this->trans('statement.saved_root'));
        } else {
            $this->service(RuntimeConfig::class)->update($data);
            $this->confirm($this->trans('statement.saved'));
        }
    }

    private function render(): string
    {
        $runtimeConfig = $this->service(RuntimeConfig::class);
        $statement = $this->service(StatementService::class);
        $token = $this->requestToken();
        $rootId = $this->currentRoot();

        $get = static fn (string $k): string => (string) $runtimeConfig->getForRoot($rootId, $k, '');

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('statement.editor_title') . '</h1>';
        $html .= '<p class="tl_info">' . $this->trans('statement.legal_disclaimer') . '</p>';
        if ($rootId > 0) {
            $html .= '<p class="tl_info">' . $this->trans('statement.domain_scope_hint') . '</p>';
        }
        $html .= '<p class="tl_info">' . $this->trans('statement.suggested_status', [
            'status' => $this->esc(StatementService::statusLabel($statement->suggestedStatus($rootId))),
        ]) . '</p>';

        $html .= '<form method="post" action="">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<div class="tl_formbody_edit">';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('statement.section_details')) . '</legend>';
        $html .= $this->text('statement_org', $this->trans('statement.org_label'), $get('statement_org'));
        $html .= $this->text('statement_url', $this->trans('statement.url_label'), $get('statement_url'));
        $html .= $this->widget('statement_status', $this->trans('statement.status_label'), $this->select('statement_status', [
            'conformant' => $this->trans('statement.status_conformant'),
            'partial' => $this->trans('statement.status_partial'),
            'nonconformant' => $this->trans('statement.status_nonconformant'),
        ], $get('statement_status') ?: 'partial'));
        $html .= $this->widget('statement_nonaccessible', $this->trans('statement.nonaccessible_label'),
            '<textarea name="statement_nonaccessible" id="statement_nonaccessible" class="tl_textarea" rows="4">' . $this->esc($get('statement_nonaccessible')) . '</textarea>');
        $html .= '</fieldset>';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->esc($this->trans('statement.section_contact')) . '</legend>';
        $html .= $this->text('statement_contact_name', $this->trans('statement.contact_name_label'), $get('statement_contact_name'));
        $html .= $this->text('statement_contact_email', $this->trans('statement.contact_email_label'), $get('statement_contact_email'));
        $html .= $this->text('statement_contact_phone', $this->trans('statement.contact_phone_label'), $get('statement_contact_phone'));
        $html .= $this->text('feedback_recipient', $this->trans('statement.feedback_recipient_label'), $get('feedback_recipient'));
        $html .= '</fieldset>';

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->trans('statement.section_creation') . '</legend>';
        $html .= $this->text('statement_prepared', $this->trans('statement.prepared_label'), $get('statement_prepared'));
        $html .= $this->widget('statement_method', $this->trans('statement.method_label'), $this->select('statement_method', [
            'self' => $this->trans('statement.method_self'),
            'external' => $this->trans('statement.method_external'),
        ], $get('statement_method') ?: 'self'));
        $html .= $this->widget('statement_enforcement', $this->trans('statement.enforcement_label'),
            '<textarea name="statement_enforcement" id="statement_enforcement" class="tl_textarea" rows="4">' . $this->esc($get('statement_enforcement')) . '</textarea>');
        $html .= '</fieldset>';

        $html .= '</div>';
        $html .= '<div class="tl_formbody_submit"><div class="tl_submit_container">';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('common.save')) . '</button>';
        $html .= '</div></div></form>';

        $html .= '<p style="color:#999;margin-top:12px;">' . $this->trans('statement.embed_hint') . '</p>';

        return $html;
    }

    private function text(string $name, string $label, string $value): string
    {
        return $this->widget($name, $label,
            '<input type="text" name="' . $this->esc($name) . '" id="' . $this->esc($name) . '" class="tl_text" value="' . $this->esc($value) . '">');
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, array $options, string $current): string
    {
        $html = '<select name="' . $this->esc($name) . '" id="' . $this->esc($name) . '" class="tl_select">';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . $this->esc($value) . '"' . ($value === $current ? ' selected' : '') . '>' . $this->esc($label) . '</option>';
        }

        return $html . '</select>';
    }

    private function widget(string $for, string $label, string $field): string
    {
        return '<div class="widget" style="margin-bottom:14px;"><h3><label for="' . $this->esc($for) . '">' . $this->esc($label) . '</label></h3>' . $field . '</div>';
    }
}
