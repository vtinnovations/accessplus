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

namespace VTInnovations\AccessPlus\Simplify;

use VTInnovations\AccessPlus\Model\SimplificationModel;

/**
 * One content element with its original text and current simplification draft.
 * View model for the backend review screen.
 */
final class SimplifyItem
{
    public function __construct(
        public readonly int $contentId,
        public readonly string $snippetKey,
        public readonly string $type,
        public readonly string $originalHtml,
        public readonly ?SimplificationModel $draft,
    ) {
    }
}
