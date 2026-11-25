<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-hardening headers on every response (registered globally in bootstrap/app.php). Nothing here changes what a page can do:
 *  - Referrer-Policy: other sites get the origin only, never the path (share codes and tournament links are in paths).
 *  - Permissions-Policy: the site uses no camera, microphone, location or payment, so a script cannot ask for them either.
 *  - Strict-Transport-Security: only on HTTPS responses; tells browsers to stay on HTTPS.
 * X-Frame-Options and X-Content-Type-Options are sent by the web server. A Content-Security-Policy is not set here: Inertia's inline page data,
 * Vite and analytics would each need an exception, so add one as a report-only policy first.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()', false);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
