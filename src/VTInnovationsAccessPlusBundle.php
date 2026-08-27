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

namespace VTInnovations\AccessPlus;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle entry point.
 *
 * getPath() returns the bundle ROOT (one level above src/) so Contao finds the
 * contao/ directory for config, languages and DCA. Returning src/ would make
 * those resources silently invisible.
 */
class VTInnovationsAccessPlusBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
