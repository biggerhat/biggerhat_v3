<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // New visitors see dark by default; Light and System remain selectable
        // from the Appearance settings page. Only the "never chose" fallback
        // is affected — an explicit cookie always wins.
        View::share('appearance', $request->cookie('appearance') ?? 'dark');
        View::share('theme', $request->cookie('theme'));

        // Cookie-consent state drives whether Blade renders the Google Analytics
        // script. `null` = undecided (show banner, no GA). `accepted` = GA loads.
        // `declined` = no GA, banner stays hidden.
        View::share('consent', $request->cookie('cookie_consent'));

        return $next($request);
    }
}
