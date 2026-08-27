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

use VTInnovations\AccessPlus\I18n\Text;

/**
 * Finding severity. The weight drives sorting and the rough accessibility
 * score; it is NOT a legal classification.
 */
enum Severity: string
{
    case Critical = 'critical';
    case Serious  = 'serious';
    case Moderate = 'moderate';
    case Minor    = 'minor';

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::Serious  => 3,
            self::Moderate => 2,
            self::Minor    => 1,
        };
    }

    public function label(): string
    {
        return Text::get('severity.' . $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => '#c0392b',
            self::Serious  => '#e08000',
            self::Moderate => '#c9a400',
            self::Minor    => '#6c8a96',
        };
    }
}
