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

/**
 * The golden rule in code (Bauplan): "Determinismus → Live-Fix. Urteil →
 * Vorschlag/Meldung." This is the single source of truth for two orthogonal
 * questions about a check id:
 *
 *   - hasAutomatedRemedy(): is there ANY assisted path to fix it (today only the
 *     AI alt-text path)? Drives the dashboard "Ein-Klick" column.
 *   - isDeterministicLiveFix(): may its findings be applied LIVE without human
 *     judgement? In the current pure-database scope NOTHING qualifies — every
 *     finding needs either content (alt, link text, labels) or a decision the
 *     DB can't make. The list grows once the bundle owns template output.
 *
 * Keeping this empty-but-explicit is the guardrail: no future code can silently
 * auto-publish a judgement call, and the regression test pins it.
 */
final class AutoFixPolicy
{
    /** Checks with an assisted (not necessarily automatic) remedy. */
    private const ASSISTED = [
        'image_alt_missing',
    ];

    /**
     * Checks whose fix is deterministic enough to apply live without review.
     * Intentionally empty in the database-only scope — see class doc.
     *
     * @var list<string>
     */
    private const DETERMINISTIC_LIVE = [];

    public function hasAutomatedRemedy(string $checkId): bool
    {
        return \in_array($checkId, self::ASSISTED, true);
    }

    public function isDeterministicLiveFix(string $checkId): bool
    {
        return \in_array($checkId, self::DETERMINISTIC_LIVE, true);
    }
}
