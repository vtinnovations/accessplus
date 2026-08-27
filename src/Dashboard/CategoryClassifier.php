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

namespace VTInnovations\AccessPlus\Dashboard;

use VTInnovations\AccessPlus\Check\FindingStatus;
use VTInnovations\AccessPlus\Fix\AutoFixPolicy;

/**
 * Maps a finding (by check id + status) to a dashboard category. The "is there
 * an automated remedy?" decision is delegated to AutoFixPolicy so the golden
 * rule lives in exactly one place.
 */
final class CategoryClassifier
{
    public function __construct(
        private readonly AutoFixPolicy $policy,
    ) {
    }

    public function classify(string $checkId, string $status): Category
    {
        if ($status === FindingStatus::Fixed->value) {
            return Category::Done;
        }

        if ($this->policy->hasAutomatedRemedy($checkId)) {
            return Category::OneClick;
        }

        return Category::Manual;
    }
}
