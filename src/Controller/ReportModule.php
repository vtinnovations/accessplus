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
use VTInnovations\AccessPlus\Check\CheckRegistry;
use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\Check\LintRunner;
use VTInnovations\AccessPlus\Check\Severity;
use VTInnovations\AccessPlus\Model\FindingModel;

/**
 * Backend report screen (BE_MOD callback). "Scan jetzt" runs the checks; each
 * finding can be triaged (Bestätigen / Ignorieren / Wieder offen). Every write
 * is POST + request token followed by a PRG redirect (Turbo). No mutating GET.
 *
 * Phase 2 scope: detect and display only — no auto-fix, no AI.
 */
final class ReportModule
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

        $action = (string) $request->request->get('accessplus_action', '');

        if ($action === 'scan') {
            $summary = $this->service(LintRunner::class)->run();
            $this->confirm($this->trans('report.scan_done_confirm', [
                'new' => $summary->created,
                'reopened' => $summary->reopened,
                'resolved' => $summary->resolved,
                'open' => $summary->openTotal,
                'score' => $summary->score,
            ]));

            return;
        }

        if ($action === 'status') {
            $this->updateStatus(
                (int) $request->request->get('finding_id', 0),
                (string) $request->request->get('new_status', ''),
            );
        }
    }

    private function updateStatus(int $findingId, string $newStatus): void
    {
        $allowed = [FindingStatus::Open->value, FindingStatus::Confirmed->value, FindingStatus::Ignored->value];
        if ($findingId <= 0 || !\in_array($newStatus, $allowed, true)) {
            return;
        }

        $finding = FindingModel::findById($findingId);
        if ($finding === null) {
            return;
        }

        $finding->status = $newStatus;
        $finding->tstamp = time();
        $finding->save();

        $this->confirm($this->trans('report.status_updated'));
    }

    private function render(): string
    {
        $token = $this->requestToken();

        $back = $this->esc($this->trans('common.back'));
        $html = '<div id="tl_buttons"><a href="contao" class="header_back" title="' . $back . '">' . $back . '</a></div>';
        $html .= $this->flashMessages();
        $html .= '<h1>' . $this->trans('report.title') . '</h1>';

        $html .= '<form method="post" action="" style="margin-bottom:16px;">';
        $html .= '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">';
        $html .= '<input type="hidden" name="accessplus_action" value="scan">';
        $html .= '<button type="submit" class="tl_submit">' . $this->esc($this->trans('report.scan_btn')) . '</button>';
        $html .= ' <span style="color:#999;">' . $this->esc($this->trans('report.scan_help')) . '</span>';
        $html .= '</form>';

        $findings = $this->openFindings();
        $html .= $this->renderScore($findings);

        if ($findings === []) {
            $html .= '<p class="tl_confirm">' . $this->esc($this->trans('report.no_open_findings')) . '</p>';

            return $html;
        }

        $labels = $this->checkLabels();

        foreach ([Severity::Critical, Severity::Serious, Severity::Moderate, Severity::Minor] as $severity) {
            $group = array_filter($findings, static fn (FindingModel $f): bool => $f->severity === $severity->value);
            if ($group === []) {
                continue;
            }

            $html .= '<fieldset class="tl_tbox block"><legend style="color:' . $severity->color() . ';">'
                . $this->esc($severity->label()) . ' (' . \count($group) . ')</legend>';
            foreach ($group as $finding) {
                $html .= $this->renderFinding($finding, $labels, $token);
            }
            $html .= '</fieldset>';
        }

        return $html;
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderFinding(FindingModel $finding, array $labels, string $token): string
    {
        $checkLabel = $labels[$finding->checkId] ?? (string) $finding->checkId;
        $isConfirmed = $finding->status === FindingStatus::Confirmed->value;

        $html = '<div style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.2);">';
        $html .= '<strong>' . $this->esc($checkLabel) . '</strong>';
        if ($finding->wcagSc !== '') {
            $html .= ' <span style="background:#2d6a2d;color:#fff;padding:1px 6px;border-radius:3px;font-size:.8em;">WCAG ' . $this->esc((string) $finding->wcagSc) . '</span>';
        } elseif (str_starts_with((string) $finding->checkId, 'axe:')) {
            $html .= ' <span style="background:#555;color:#ddd;padding:1px 6px;border-radius:3px;font-size:.8em;" title="' . $this->esc($this->trans('common.best_practice_title')) . '">' . $this->esc($this->trans('common.best_practice_badge')) . '</span>';
        }
        if ($isConfirmed) {
            $html .= ' <span style="color:#c0392b;font-weight:bold;">' . $this->esc($this->trans('report.confirmed_badge')) . '</span>';
        }
        if ((int) $finding->occurrences > 1) {
            $html .= ' <span style="color:#c0392b;font-weight:bold;">' . $this->esc($this->trans('common.occurrences_pages', ['count' => (int) $finding->occurrences])) . '</span>';
        }
        // axe ships English text; show a German explanation for known rules.
        $message = (string) $finding->message;
        $suggestion = (string) ($finding->suggestion ?? '');
        if (str_starts_with((string) $finding->checkId, 'axe:')) {
            $german = \VTInnovations\AccessPlus\Frontend\AxeMessages::german(substr((string) $finding->checkId, 4));
            if ($german !== null) {
                $message = $german['title'];
                $help = '';
                if (preg_match('#https?://\S+#', $suggestion, $m) === 1) {
                    $help = "\nMehr: " . $m[0];
                }
                $suggestion = $german['hint'] . $help;
            }
        }

        $html .= '<br>' . $this->esc($message);
        $html .= '<br><span style="color:#888;">' . $this->esc((string) $finding->elementLabel) . '</span>';
        if ($suggestion !== '') {
            $html .= '<br><span style="color:#36a957;">↳ ' . nl2br($this->esc($suggestion)) . '</span>';
        }

        $html .= '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">';

        // "Jetzt fixen" → native Contao edit mask of the underlying record.
        $editUrl = $this->editUrl((string) $finding->ptable, (int) $finding->pid);
        if ($editUrl !== null) {
            $html .= '<a href="' . $this->esc($editUrl) . '" target="_blank" rel="noopener" class="tl_submit" style="padding:2px 10px;text-decoration:none;background:#197;">' . $this->esc($this->trans('common.fix_now')) . '</a>';
        }

        // Live "show on page" for frontend (axe) findings.
        if (str_starts_with((string) $finding->checkId, 'axe:') && (string) $finding->sampleUrl !== '') {
            $html .= '<a href="' . $this->esc($this->highlightUrl((int) $finding->id)) . '" target="_blank" rel="noopener" class="tl_submit" style="padding:2px 8px;text-decoration:none;">' . $this->esc($this->trans('common.show_on_page')) . '</a>';
        }

        if (!$isConfirmed) {
            $html .= $this->statusButton((int) $finding->id, FindingStatus::Confirmed->value, $this->trans('report.confirm_btn'), $token);
        } else {
            $html .= $this->statusButton((int) $finding->id, FindingStatus::Open->value, $this->trans('report.reopen_btn'), $token);
        }
        $html .= $this->statusButton((int) $finding->id, FindingStatus::Ignored->value, $this->trans('report.ignore_btn'), $token);
        $html .= '</div></div>';

        return $html;
    }

    private function statusButton(int $findingId, string $status, string $label, string $token): string
    {
        return '<form method="post" action="" style="margin:0;">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $this->esc($token) . '">'
            . '<input type="hidden" name="accessplus_action" value="status">'
            . '<input type="hidden" name="finding_id" value="' . $findingId . '">'
            . '<input type="hidden" name="new_status" value="' . $this->esc($status) . '">'
            . '<button type="submit" class="tl_submit" style="padding:2px 8px;">' . $this->esc($label) . '</button>'
            . '</form>';
    }

    /**
     * @param list<FindingModel> $findings
     */
    private function renderScore(array $findings): string
    {
        $penalty = 0;
        foreach ($findings as $finding) {
            $severity = Severity::tryFrom((string) $finding->severity);
            if ($severity !== null) {
                $penalty += $severity->weight();
            }
        }
        $score = max(0, 100 - $penalty * 2);

        return '<p style="font-size:1.1em;">' . $this->trans('report.score_line', ['score' => $score, 'count' => \count($findings)]) . '</p>';
    }

    /**
     * @return list<FindingModel>
     */
    private function openFindings(): array
    {
        $rootId = $this->currentRoot();
        $columns = ['(status = ? OR status = ?)'];
        $values = [FindingStatus::Open->value, FindingStatus::Confirmed->value];
        if ($rootId > 0) {
            $columns[0] .= ' AND rootId = ?';
            $values[] = $rootId;
        }

        $collection = FindingModel::findBy($columns, $values, ['order' => 'severity ASC, tstamp DESC']);

        if ($collection === null) {
            return [];
        }

        $out = [];
        foreach ($collection as $model) {
            $out[] = $model;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function checkLabels(): array
    {
        $labels = [];
        foreach ($this->service(CheckRegistry::class)->all() as $check) {
            $labels[$check->getId()] = $check->getLabel();
        }

        return $labels;
    }
}
