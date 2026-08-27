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

use VTInnovations\AccessPlus\I18n\Text;

/**
 * The three honest buckets of the dashboard (Bauplan Phase 4):
 *
 *   - Done     ✅ already handled (fixed/applied).
 *   - OneClick 🔘 an automated remedy is available (today: AI alt text).
 *   - Manual   👤 needs a human edit; the finding carries the guidance.
 *
 * The split is the product's core honesty: no pseudo-checkmarks — what truly
 * needs a person is shown as such.
 */
enum Category: string
{
    case Done     = 'done';
    case OneClick = 'oneclick';
    case Manual   = 'manual';

    public function icon(): string
    {
        return match ($this) {
            self::Done     => '✅',
            self::OneClick => '🔘',
            self::Manual   => '👤',
        };
    }

    public function label(): string
    {
        return Text::get('category.' . $this->value);
    }
}
