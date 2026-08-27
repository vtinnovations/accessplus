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

namespace VTInnovations\AccessPlus\Fix;

use Contao\BackendUser;
use VTInnovations\AccessPlus\Model\AuditModel;

/**
 * Records applied fixes into tl_accessplus_audit so every change is attributable and
 * reversible (the project guidelines §3.3 audit; Bauplan Undo/Audit). Append-only.
 */
final class AuditLogger
{
    public function record(
        string $action,
        string $targetTable,
        string $targetUuid,
        int $targetId,
        string $field,
        string $lang,
        ?string $before,
        bool $beforeAbsent,
        ?string $after,
    ): AuditModel {
        $now = time();

        $entry = new AuditModel();
        $entry->tstamp = $now;
        $entry->createdAt = $now;
        $entry->action = $action;
        $entry->targetTable = $targetTable;
        $entry->targetUuid = $targetUuid;
        $entry->targetId = $targetId;
        $entry->field = $field;
        $entry->lang = $lang;
        $entry->beforeValue = $before;
        $entry->beforeAbsent = $beforeAbsent ? '1' : '';
        $entry->afterValue = $after;
        $entry->userName = $this->currentUserName();
        $entry->undone = '';
        $entry->save();

        return $entry;
    }

    private function currentUserName(): string
    {
        try {
            $user = BackendUser::getInstance();
            $name = (string) ($user->username ?? '');

            return $name !== '' ? $name : 'system';
        } catch (\Throwable) {
            // CLI / no backend session.
            return 'cli';
        }
    }
}
