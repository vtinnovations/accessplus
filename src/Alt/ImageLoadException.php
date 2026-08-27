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

namespace VTInnovations\AccessPlus\Alt;

/**
 * Thrown when an image cannot be loaded for analysis (missing, too big, wrong
 * type, traversal attempt). Carries no sensitive data.
 */
final class ImageLoadException extends \RuntimeException
{
}
