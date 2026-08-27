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
 * Collects all tagged checks. Adding a check is "implement CheckInterface" —
 * the _instanceof tag + tagged_iterator wire it in automatically.
 */
final class CheckRegistry
{
    /** @var list<CheckInterface> */
    private array $checks;

    /**
     * @param iterable<CheckInterface> $checks
     */
    public function __construct(iterable $checks)
    {
        $this->checks = is_array($checks) ? array_values($checks) : iterator_to_array($checks, false);
    }

    /**
     * @return list<CheckInterface>
     */
    public function all(): array
    {
        return $this->checks;
    }
}
