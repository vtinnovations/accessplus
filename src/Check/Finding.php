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

namespace VTInnovations\AccessPlus\Check;

/**
 * One detected accessibility issue, as emitted by a check. This is a transient
 * value object — the LintRunner turns it into a persisted tl_accessplus_finding row
 * (or updates an existing one matched by fingerprint).
 *
 * The fingerprint is the stable identity of "this issue, here": same check,
 * same source row, same field. It makes re-scans idempotent — a second run
 * neither duplicates an open finding nor loses a human's Confirmed/Ignored
 * decision (the project guidelines §4).
 */
final class Finding
{
    /**
     * @param list<string> $wcagCriteria e.g. ['1.1.1']
     */
    public function __construct(
        public readonly string $checkId,
        public readonly array $wcagCriteria,
        public readonly Severity $severity,
        public readonly SourceType $sourceType,
        public readonly string $ptable,
        public readonly int $pid,
        public readonly string $field,
        public readonly string $elementLabel,
        public readonly string $message,
        public readonly string $suggestion = '',
    ) {
    }

    public function fingerprint(): string
    {
        return sha1(implode('|', [
            $this->checkId,
            $this->ptable,
            (string) $this->pid,
            $this->field,
        ]));
    }

    public function wcagString(): string
    {
        return implode(', ', $this->wcagCriteria);
    }
}
