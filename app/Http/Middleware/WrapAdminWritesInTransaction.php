<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs every state-changing admin request in one database transaction, so a
 * model save plus its relation syncs, audit rows and counters either all land
 * or none do — without each controller having to remember to wrap itself.
 * Files written to disk are not rolled back.
 */
class WrapAdminWritesInTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return DB::transaction(fn () => $next($request));
    }
}
