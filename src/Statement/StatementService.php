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

namespace VTInnovations\AccessPlus\Statement;

use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\I18n\Text;
use VTInnovations\AccessPlus\Model\FindingModel;
use VTInnovations\AccessPlus\State\RuntimeConfig;

/**
 * Assembles the accessibility statement data from the admin-entered config plus
 * the current monitoring picture. The conformance status is only a SUGGESTION
 * derived from open findings — the admin decides and is responsible (this is
 * not legal advice; Marketing-Leitplanke §4 / the project guidelines).
 */
final class StatementService
{
    public function __construct(
        private readonly RuntimeConfig $runtimeConfig,
    ) {
    }

    /**
     * @param int $rootId Statement for one site root (Modell 2); 0 = install-wide.
     *                    Per-root fields fall back to the global value when unset.
     *
     * @return array<string, mixed>
     */
    public function data(int $rootId = 0): array
    {
        $get = fn (string $k, string $d = ''): string => (string) $this->runtimeConfig->getForRoot($rootId, $k, $d);

        return [
            'org' => $get('statement_org'),
            'url' => $get('statement_url'),
            'status' => $get('statement_status', 'partial'),
            'nonaccessible' => $get('statement_nonaccessible'),
            'contactName' => $get('statement_contact_name'),
            'contactEmail' => $get('statement_contact_email'),
            'contactPhone' => $get('statement_contact_phone'),
            'prepared' => $get('statement_prepared'),
            'method' => $get('statement_method', 'self'),
            'enforcement' => $get('statement_enforcement'),
        ];
    }

    /**
     * A status SUGGESTION from current open findings. Never authoritative.
     * Scoped to $rootId (0 = whole install).
     */
    public function suggestedStatus(int $rootId = 0): string
    {
        $open = (int) FindingModel::countBy(...$this->scoped('status = ? OR status = ?', [FindingStatus::Open->value, FindingStatus::Confirmed->value], $rootId));

        if ($open === 0) {
            return 'conformant';
        }

        $serious = (int) FindingModel::countBy(...$this->scoped(
            '(status = ? OR status = ?) AND (severity = ? OR severity = ?)',
            [FindingStatus::Open->value, FindingStatus::Confirmed->value, 'critical', 'serious'],
            $rootId,
        ));

        return $serious > 10 ? 'nonconformant' : 'partial';
    }

    /**
     * @param list<mixed> $values
     *
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function scoped(string $where, array $values, int $rootId): array
    {
        if ($rootId > 0) {
            $where .= ' AND rootId = ?';
            $values[] = $rootId;
        }

        return [[$where], $values];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'conformant' => Text::get('statement.status_conformant'),
            'nonconformant' => Text::get('statement.status_nonconformant'),
            default => Text::get('statement.status_partial'),
        };
    }
}
