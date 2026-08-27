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

namespace VTInnovations\AccessPlus\Subtitle;

/**
 * Local media problem (missing file, too large, unreadable) — distinct from a
 * provider/network AiException. Message is safe to show to the admin.
 */
final class MediaLoadException extends \RuntimeException
{
}
