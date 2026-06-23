<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a correlation id to every request: honours an inbound X-Request-Id
 * (so a caller can thread its own id through) or mints a UUID. The id is pushed
 * into the Context facade — which Laravel automatically includes in log records
 * AND serialises onto queued jobs — so a single payment can be traced from the
 * HTTP request through ProcessPaymentJob and back. It is also echoed on the
 * response header for the client / a support ticket to quote.
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get(self::HEADER) ?: (string) Str::uuid();

        Context::add('request_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
