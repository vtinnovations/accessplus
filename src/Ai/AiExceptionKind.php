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

namespace VTInnovations\AccessPlus\Ai;

enum AiExceptionKind: string
{
    case Auth            = 'auth';
    case RateLimit       = 'rate_limit';
    case QuotaExceeded   = 'quota_exceeded';
    case Transport       = 'transport';
    case BadRequest      = 'bad_request';
    case ServerError     = 'server_error';
    case InvalidResponse = 'invalid_response';
    case PromptFiltered  = 'prompt_filtered';
    case EgressBlocked   = 'egress_blocked';
    case Unknown         = 'unknown';
}
