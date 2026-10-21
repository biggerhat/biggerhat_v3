<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-secret gate for the Discord bot's telemetry endpoint. When no secret
 * is configured the endpoint is disabled (404) rather than open.
 */
class AuthenticateBotTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.bot.telemetry_token');
        if ($secret === '') {
            abort(404);
        }

        $provided = (string) $request->bearerToken();
        if (! hash_equals($secret, $provided)) {
            abort(401);
        }

        return $next($request);
    }
}
