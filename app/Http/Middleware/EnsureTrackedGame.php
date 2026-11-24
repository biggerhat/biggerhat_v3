<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a game that was logged by hand out of everything that belongs to a game being played: the play screen, setup, deployment,
 * the board, Fate, joining, abandoning and observing. Its own summary page is the only thing it has, so the two addresses that
 * show a game send it there and everything else is not found.
 */
class EnsureTrackedGame
{
    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');

        if ($game instanceof Game && $game->isLogged()) {
            if (in_array($request->route()?->getName(), ['games.show', 'games.summary'], true)) {
                return redirect()->route('games.logged.show', $game);
            }

            abort(404);
        }

        return $next($request);
    }
}
