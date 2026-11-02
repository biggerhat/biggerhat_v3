<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /** Accent themes the client can set (see `Theme` in composables/useAppearance.ts); anything else is ignored. */
    private const ACCENT_THEMES = ['arcanists', 'bayou', 'explorerssociety', 'guild', 'neverborn', 'outcasts', 'resurrectionists', 'tenthunders'];

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
        $theme = $request->cookie('theme');
        View::share('theme', in_array($theme, self::ACCENT_THEMES, true) ? $theme : null);

        // Reader text size, applied as `data-text-size` on <html> so the first paint is already scaled.
        $textSize = $request->cookie('text_size');
        View::share('textSize', in_array($textSize, ['large', 'xlarge'], true) ? $textSize : null);

        // Cookie-consent state drives whether Blade renders the Google Analytics
        // script. `null` = undecided (show banner, no GA). `accepted` = GA loads.
        // `declined` = no GA, banner stays hidden.
        View::share('consent', $request->cookie('cookie_consent'));

        return $next($request);
    }
}
