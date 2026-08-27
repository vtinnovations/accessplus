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
 * A single accessibility check. Implementations are tagged
 * `vtinnovations.accessplus.check` (auto-applied via _instanceof) and gathered by the
 * CheckRegistry.
 *
 * Phase 2 contract: checks read the Contao DATABASE only — no rendering, no
 * HTTP. They must be deterministic and side-effect-free (scan() never writes;
 * the LintRunner owns persistence).
 */
interface CheckInterface
{
    /** Stable identifier, e.g. 'image_alt_missing'. Part of the fingerprint. */
    public function getId(): string;

    /** Human-readable name for the report UI. */
    public function getLabel(): string;

    /**
     * Yields every issue this check finds in the current content.
     *
     * @return iterable<Finding>
     */
    public function scan(): iterable;
}
