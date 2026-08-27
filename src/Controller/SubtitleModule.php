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
use VTInnovations\AccessPlus\Ai\AiException;
use VTInnovations\AccessPlus\State\RuntimeConfig;
use VTInnovations\AccessPlus\Subtitle\MediaLoadException;
use VTInnovations\AccessPlus\Subtitle\SubtitleCandidate;
use VTInnovations\AccessPlus\Subtitle\SubtitleService;

/**
 * Backend screen for AI subtitles (BE_MOD callback). Lists audio/video files,
 * generates a WebVTT DRAFT via Whisper (opt-in, POST + token + PRG, respects the
 * egress switch), then review: edit the VTT, approve (writes a .vtt file next to
 * the source) or reject. Nothing is published automatically.
 */
final class SubtitleModule
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

        $service = $this->service(SubtitleService::class);
        $action = (string) $request->request->get('accessplus_action', '');

        switch ($action) {
            case 'generate':
                $uuid = (string) $request->request->get('uuid', '');
                $lang = (string) $request->request->get('lang', '');
                try {
                    $track = $service->generate($uuid, $lang);
                    $this->confirm($this->trans('subtitle.generated_confirm', [
                        'lang' => $track->lang,
                        'ms' => (int) $track->durationMs,
                    ]));
                } catch (AiException $e) {
                    $this->error($this->trans('subtitle.generate_error_prefix', ['message' => $e->getMessage()]));
                } catch (MediaLoadException $e) {
                    $this->error($e->getMessage());
                }
                break;

            case 'savedraft':
                $service->saveDraft((int) $request->request->get('track_id', 0), (string) $request->request->get('vtt', ''));
                $this->confirm($this->trans('subtitle.savedraft_confirm'));
                break;

            case 'approve':
                $path = $service->approve((int) $request->request->get('track_id', 0));
                $path !== null
                    ? $this->confirm($this->trans('subtitle.approve_confirm', ['path' => $path]))
                    : $this->error($this->trans('subtitle.approve_error'));
                break;

            case 'reject':
                $service->reject((int) $request->request->get('track_id', 0));
                $this->confirm($this->trans('subtitle.reject_confirm'));
                break;
        }
    }

    private function render(): string
    {
        $token = $this->requestToken();
        $blocked = $this->service(RuntimeConfig::class)->externalCallsBlocked();
        $languages = $this->languages();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('subtitle.title') . '</h1>';
        $html .= '<p class="tl_help">' . $this->trans('subtitle.help') . '</p>';

        if ($blocked) {
            $html .= '<p class="tl_info">' . $this->esc($this->trans('subtitle.egress_blocked')) . '</p>';
        }

        $candidates = $this->service(SubtitleService::class)->listCandidates();
        if ($candidates === []) {
            $html .= '<p class="tl_confirm">' . $this->esc($this->trans('subtitle.no_media')) . '</p>';

            return $html;
        }

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->trans('subtitle.media_legend', ['count' => \count($candidates)]) . '</legend>';
        foreach ($candidates as $candidate) {
            $html .= $this->renderCandidate($candidate, $token, $languages, $blocked);
        }
        $html .= '</fieldset>';

        return $html;
    }

    private function renderCandidate(SubtitleCandidate $c, string $token, array $languages, bool $blocked): string
    {
        $sizeMb = $c->sizeBytes > 0 ? number_format($c->sizeBytes / 1024 / 1024, 1, ',', '.') . ' MB' : '–';

        $html = '<div style="padding:10px 0;border-bottom:1px solid rgba(128,128,128,.2);">';
        $html .= '<strong>' . $this->esc($c->path) . '</strong> <span style="color:#888;">(' . $sizeMb . ')</span>';
        if ($c->used) {
            $html .= ' <span style="color:#3a7;font-weight:bold;">' . $this->esc($this->trans('subtitle.used_badge')) . '</span>';
        }

        if ($c->tooLarge()) {
            $html .= '<p class="tl_error" style="margin:6px 0 0;">' . $this->esc($this->trans('subtitle.too_large_error')) . '</p>';
            $html .= '</div>';

            return $html;
        }

        // Generate form (per language).
        $html .= '<form method="post" action="" style="margin:6px 0 0;display:flex;gap:6px;align-items:center;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="generate">';
        $html .= '<input type="hidden" name="uuid" value="' . $this->esc($c->uuid) . '">';
        $html .= '<label>' . $this->esc($this->trans('subtitle.lang_label')) . ' <select name="lang" class="tl_select">';
        foreach ($languages as $code) {
            $html .= '<option value="' . $this->esc($code) . '">' . $this->esc($code) . '</option>';
        }
        $html .= '</select></label> ';
        $label = $c->track !== null ? $this->trans('subtitle.regenerate_btn') : $this->trans('subtitle.generate_btn');
        $html .= '<button type="submit" class="tl_submit"' . ($blocked ? ' disabled' : '') . '>' . $this->esc($label) . '</button>';
        $html .= '</form>';

        // Existing track / draft review.
        if ($c->track !== null) {
            $html .= $this->renderTrack($c, $token);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderTrack(SubtitleCandidate $c, string $token): string
    {
        $track = $c->track;
        $status = (string) $track->status;

        $badge = match ($status) {
            'applied' => '<span style="color:#3a7;font-weight:bold;">' . $this->esc($this->trans('subtitle.status_applied')) . '</span>',
            'rejected' => '<span style="color:#a33;">' . $this->esc($this->trans('subtitle.status_rejected')) . '</span>',
            default => '<span style="color:#c80;font-weight:bold;">' . $this->esc($this->trans('subtitle.status_draft')) . '</span>',
        };

        $html = '<div style="margin-top:8px;padding:8px;background:rgba(128,128,128,.06);border-radius:4px;">';
        $html .= $this->esc($this->trans('subtitle.status_label')) . ' ' . $badge . ' &nbsp;·&nbsp; ' . $this->esc($this->trans('subtitle.lang_label')) . ' ' . $this->esc((string) $track->lang);
        if ($status === 'applied' && (string) $track->vttPath !== '') {
            $html .= '<br>' . $this->esc($this->trans('subtitle.file_label')) . ' <code>' . $this->esc((string) $track->vttPath) . '</code>';
        }

        if ($status === 'pending') {
            $html .= '<form method="post" action="" style="margin-top:6px;">';
            $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
            $html .= '<input type="hidden" name="track_id" value="' . (int) $track->id . '">';
            $html .= '<textarea name="vtt" rows="10" class="tl_textarea" style="width:100%;font-family:monospace;">'
                . $this->esc((string) $track->vtt) . '</textarea>';
            $html .= '<div style="margin-top:6px;display:flex;gap:6px;">';
            $html .= '<button type="submit" name="accessplus_action" value="savedraft" class="tl_submit">' . $this->esc($this->trans('subtitle.savedraft_btn')) . '</button>';
            $html .= '<button type="submit" name="accessplus_action" value="approve" class="tl_submit">' . $this->esc($this->trans('subtitle.approve_btn')) . '</button>';
            $html .= '<button type="submit" name="accessplus_action" value="reject" class="tl_submit">' . $this->esc($this->trans('common.discard_btn')) . '</button>';
            $html .= '</div></form>';
            $html .= '<p class="tl_help" style="margin-top:4px;">' . $this->trans('subtitle.track_help') . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @return list<string>
     */
    private function languages(): array
    {
        $langs = $this->service(RuntimeConfig::class)->get('languages', ['de']);
        if (!\is_array($langs) || $langs === []) {
            return ['de'];
        }

        $out = [];
        foreach ($langs as $lang) {
            $lang = strtolower(trim((string) $lang));
            if ($lang !== '') {
                $out[$lang] = $lang;
            }
        }

        return array_values($out) ?: ['de'];
    }
}
