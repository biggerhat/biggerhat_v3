<?php

namespace App\Http\Middleware;

use App\Support\CampaignAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-facing gate for M4E Campaign Mode. Returns 404 — not 403 — when a
 * visitor fails the check, so the feature's existence stays hidden while
 * pre-release. Admin catalog routes use standard `permission:` middleware
 * (403 is fine there since admins shouldn't be surprised by 404s).
 */
class EnsureCampaignAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CampaignAccess::canUse($request->user())) {
            abort(404);
        }

        return $next($request);
    }
}
