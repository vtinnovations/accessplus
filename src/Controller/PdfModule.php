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
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\Pdf\PdfScanService;

/**
 * Backend module for PDF accessibility (BE_MOD callback). "PDFs prüfen" analyses
 * the linked PDFs (title/language/tagging) and records concrete findings with
 * remediation guidance. POST + token + PRG. Read-only analysis — PDFs are never
 * rewritten (pure-PHP rewriting of arbitrary PDFs would risk corruption).
 */
final class PdfModule
{
    use BackendModuleHelper;

    private const CHECK_IDS = ['pdf_no_title', 'pdf_no_lang', 'pdf_not_tagged'];

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

        if ((string) $request->request->get('accessplus_action', '') === 'scan') {
            $summary = $this->service(PdfScanService::class)->scan();
            $this->confirm($this->trans('pdf.scan_done_confirm', [
                'checked' => $summary->checked,
                'issues' => $summary->issues,
                'unknown' => $summary->unknown,
                'unreadable' => $summary->unreadable,
            ]));
        }
    }

    private function render(): string
    {
        $token = $this->requestToken();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('pdf.title') . '</h1>';

        $html .= '<form method="post" action="" style="margin-bottom:16px;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="scan">';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('pdf.scan_btn')) . '</button>';
        $html .= ' <span style="color:#999;">' . $this->esc($this->trans('pdf.scan_help')) . '</span>';
        $html .= '</form>';

        $findings = $this->openFindings();
        if ($findings === []) {
            $html .= '<p class="tl_confirm">' . $this->esc($this->trans('pdf.no_issues')) . '</p>';

            return $html;
        }

        $html .= '<fieldset class="tl_tbox block"><legend>' . $this->trans('pdf.open_issues_legend', ['count' => \count($findings)]) . '</legend>';
        foreach ($findings as $finding) {
            $html .= '<div style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.2);">';
            $html .= '<strong>' . $this->esc((string) $finding->elementLabel) . '</strong>';
            if ($finding->wcagSc !== '') {
                $html .= ' <span style="background:#2d6a2d;color:#fff;padding:1px 6px;border-radius:3px;font-size:.8em;">WCAG ' . $this->esc((string) $finding->wcagSc) . '</span>';
            }
            $html .= '<br>' . $this->esc((string) $finding->message);
            if (($finding->suggestion ?? '') !== '') {
                $html .= '<br><span style="color:#36a957;">↳ ' . $this->esc((string) $finding->suggestion) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</fieldset>';

        $html .= '<p style="color:#999;margin-top:12px;">' . $this->esc($this->trans('pdf.disclaimer')) . '</p>';

        return $html;
    }

    /**
     * @return list<FindingModel>
     */
    private function openFindings(): array
    {
        $placeholders = implode(',', array_fill(0, \count(self::CHECK_IDS), '?'));
        $values = self::CHECK_IDS;
        $values[] = 'fixed';

        $collection = FindingModel::findBy(
            ['checkId IN (' . $placeholders . ') AND status != ?'],
            $values,
            ['order' => 'severity ASC, tstamp DESC'],
        );

        if ($collection === null) {
            return [];
        }

        $out = [];
        foreach ($collection as $model) {
            if ($model->status !== 'ignored') {
                $out[] = $model;
            }
        }

        return $out;
    }
}
