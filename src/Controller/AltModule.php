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
use VTInnovations\AccessPlus\Alt\MetaWriteResult;
use VTInnovations\AccessPlus\Model\AltSuggestionModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Backend screen for AI alt-text proposals (BE_MOD callback). Generate (opt-in,
 * POST + token + PRG redirect, respects the egress switch), then review each
 * proposal: Übernehmen writes into tl_files.meta ONLY if the slot is empty;
 * Verwerfen discards. Nothing is published automatically.
 */
final class AltModule
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

        $service = $this->service(AltSuggestionService::class);
        $action = (string) $request->request->get('accessplus_action', '');

        switch ($action) {
            case 'generate':
                $limit = max(1, min(100, (int) $request->request->get('limit', 25)));
                $summary = $service->generateForMissing($limit);
                $summary->blocked || $summary->errors > 0
                    ? $this->error($summary->message)
                    : $this->confirm($summary->message);
                break;

            case 'approve':
                $altEdit = $request->request->get('alt');
                $result = $service->approve(
                    (int) $request->request->get('suggestion_id', 0),
                    \is_string($altEdit) ? $altEdit : null,
                );
                if ($result === MetaWriteResult::Written) {
                    $this->confirm($this->trans('alt.approved_confirm'));
                } else {
                    $this->error($result === MetaWriteResult::SkippedManual
                        ? $this->trans('alt.skipped_manual_error')
                        : $this->trans('alt.file_not_found_error'));
                }
                break;

            case 'reject':
                $service->reject((int) $request->request->get('suggestion_id', 0));
                $this->confirm($this->trans('common.suggestion_discarded'));
                break;
        }
    }

    private function render(): string
    {
        $token = $this->requestToken();
        $blocked = $this->service(RuntimeConfig::class)->externalCallsBlocked();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('alt.title') . '</h1>';

        $html .= '<form method="post" action="" style="margin-bottom:16px;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="generate">';
        $html .= '<label>' . $this->esc($this->trans('alt.limit_label')) . ' <input type="number" name="limit" value="25" min="1" max="100" class="tl_text" style="width:80px;"></label> ';
        $html .= '<button type="submit" class="tl_submit"' . ($blocked ? ' disabled title="' . $this->esc($this->trans('common.egress_blocked_title')) . '"' : '') . '>' . $this->esc($this->trans('alt.generate_btn')) . '</button>';
        if ($blocked) {
            $html .= '<p class="tl_info">' . $this->esc($this->trans('alt.egress_blocked_note')) . '</p>';
        } else {
            $html .= '<p class="tl_help">' . $this->esc($this->trans('alt.help')) . '</p>';
        }
        $html .= '</form>';

        $pending = $this->pendingSuggestions();
        if ($pending === []) {
            $html .= '<p class="tl_confirm">' . $this->esc($this->trans('alt.no_pending')) . '</p>';

            return $html;
        }

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->trans('alt.pending_legend', ['count' => \count($pending)]) . '</legend>';
        foreach ($pending as $suggestion) {
            $html .= $this->renderSuggestion($suggestion, $token);
        }
        $html .= '</fieldset>';

        return $html;
    }

    private function renderSuggestion(AltSuggestionModel $suggestion, string $token): string
    {
        $decorative = $suggestion->decorative === '1';
        $path = (string) $suggestion->filePath;

        $html = '<div style="padding:10px 0;border-bottom:1px solid rgba(128,128,128,.2);display:flex;gap:14px;align-items:flex-start;">';

        // Thumbnail so the editor sees exactly which image this is about.
        $thumb = $this->thumbUrl($path);
        if ($thumb !== null) {
            $html .= '<a href="' . $this->esc($thumb) . '" target="_blank" rel="noopener" style="flex:0 0 auto;">'
                . '<img src="' . $this->esc($thumb) . '" alt="" loading="lazy" '
                . 'style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid rgba(128,128,128,.25);background:rgba(128,128,128,.06);"></a>';
        }

        $html .= '<div style="flex:1;min-width:0;">';
        $html .= '<strong style="word-break:break-all;">' . $this->esc($path) . '</strong>';
        $html .= ' <span style="color:#888;">[' . $this->esc((string) $suggestion->lang) . ']</span>';
        if ($decorative) {
            $html .= ' <span style="color:#6c8a96;font-weight:bold;">' . $this->esc($this->trans('alt.decorative_badge')) . '</span>';
        }

        // One form: edit the alt text, then approve (writes the edited value) or
        // reject. An empty field on approve = decorative (deliberate empty alt).
        $html .= '<form method="post" action="" style="margin:6px 0 0;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="suggestion_id" value="' . (int) $suggestion->id . '">';
        $html .= '<textarea name="alt" rows="2" class="tl_textarea" style="width:100%;" placeholder="' . $this->esc($this->trans('alt.alt_placeholder')) . '">'
            . $this->esc($decorative ? '' : (string) $suggestion->suggestion) . '</textarea>';
        $html .= '<div style="margin-top:6px;display:flex;gap:6px;">';
        $html .= '<button type="submit" name="accessplus_action" value="approve" class="tl_submit">' . $this->esc($this->trans('common.apply_btn')) . '</button>';
        $html .= '<button type="submit" name="accessplus_action" value="reject" class="tl_submit">' . $this->esc($this->trans('common.discard_btn')) . '</button>';
        $html .= '</div></form></div></div>';

        return $html;
    }

    /**
     * Public URL of a files/ image for the backend preview, base-path aware
     * (sub-directory installs) and path-segment encoded. Only files under files/.
     */
    private function thumbUrl(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'files/')) {
            return null;
        }

        $request = $this->currentRequest();
        $base = $request !== null ? $request->getBasePath() : '';
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $base . '/' . $encoded;
    }

    /**
     * @return list<AltSuggestionModel>
     */
    private function pendingSuggestions(): array
    {
        $collection = AltSuggestionModel::findByStatus('pending', ['order' => 'tstamp DESC']);
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
