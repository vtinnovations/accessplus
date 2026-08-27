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

namespace VTInnovations\AccessPlus\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use VTInnovations\AccessPlus\Exchange\InboundUpdate;

/**
 * Public entry point for server-initiated state updates.
 *
 * Deliberately thin: it decides the method, hands the request to the application
 * service and turns the result into a response. No cryptography, no key
 * material, no storage and no policy live here — the visible route is the one
 * thing that cannot be hidden, so it is also the one thing that knows nothing.
 *
 * The route is intentionally outside the backend firewall (the caller is a
 * server, not a logged-in user) and intentionally exempt from browser CSRF —
 * trust comes exclusively from the signed request.
 */
final class ServiceCallbackController
{
    public function __construct(
        private readonly InboundUpdate $update,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['status' => 'method_not_allowed'], 405, ['Allow' => 'POST']);
        }

        try {
            $result = $this->update->handle($request);
        } catch (\Throwable) {
            // Never leak an internal failure shape to an unauthenticated caller.
            return new JsonResponse(['status' => 'rejected'], 500);
        }

        return new JsonResponse($result['body'], $result['status']);
    }
}
