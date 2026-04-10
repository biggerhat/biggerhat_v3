<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\GameStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Games
 */
class GameController extends Controller
{
    /**
     * List recent completed games
     *
     * @queryParam faction string Filter by player faction. Example: arcanists
     * @queryParam per_page int Results per page (max 50). Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = Game::where('status', GameStatusEnum::Completed)
            ->with(['players.user:id,name', 'strategy:id,name', 'winner:id,name']);

        if ($request->filled('faction')) {
            $query->whereHas('players', fn ($q) => $q->where('faction', $request->get('faction')));
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $games = $query->latest('completed_at')->paginate($perPage);

        return response()->json($games->through(fn (Game $g) => [
            'uuid' => $g->uuid,
            'name' => $g->name,
            'encounter_size' => $g->encounter_size,
            'strategy' => $g->strategy ? $g->strategy->name : null,
            'is_solo' => $g->is_solo,
            'completed_at' => $g->completed_at?->toISOString(),
            'players' => $g->players->map(fn ($p) => [
                'name' => ($p->user ? $p->user->name : null) ?? $p->opponent_name ?? 'Player '.$p->slot,
                'faction' => $p->getRawOriginal('faction'),
                'master' => $p->master_name,
                'vp' => $p->total_points,
            ])->values()->all(),
            'winner' => $g->winner ? $g->winner->name : null,
            'is_tie' => $g->is_tie,
            'url' => route('games.summary', $g->uuid),
        ]));
    }

    /**
     * View a completed game summary
     */
    public function show(Game $game): JsonResponse
    {
        if ($game->status !== GameStatusEnum::Completed) {
            abort(404);
        }

        $game->load(['players.user:id,name', 'players.turns', 'strategy:id,name', 'winner:id,name']);

        $turns = [];
        $maxTurn = $game->players->flatMap(fn ($p) => $p->turns)->max('turn_number') ?? 0;
        for ($t = 1; $t <= $maxTurn; $t++) {
            $turnData = [];
            foreach ($game->players as $player) {
                $turn = $player->turns->firstWhere('turn_number', $t);
                $sVp = $turn ? $turn->strategy_points : 0;
                $schVp = $turn ? $turn->scheme_points : 0;
                $turnData[] = [
                    'player' => ($player->user ? $player->user->name : null) ?? $player->opponent_name ?? 'Player '.$player->slot,
                    'strategy_vp' => $sVp,
                    'scheme_vp' => $schVp,
                    'total_vp' => $sVp + $schVp,
                ];
            }
            $turns[] = ['turn' => $t, 'scores' => $turnData];
        }

        return response()->json([
            'uuid' => $game->uuid,
            'name' => $game->name,
            'encounter_size' => $game->encounter_size,
            'strategy' => $game->strategy?->name,
            'deployment' => $game->deployment ? $game->deployment->label() : null,
            'is_solo' => $game->is_solo,
            'completed_at' => $game->completed_at?->toISOString(),
            'players' => $game->players->map(fn ($p) => [
                'name' => ($p->user ? $p->user->name : null) ?? $p->opponent_name ?? 'Player '.$p->slot,
                'faction' => $p->getRawOriginal('faction'),
                'master' => $p->master_name,
                'total_vp' => $p->total_points,
                'role' => $p->role,
            ])->values()->all(),
            'turns' => $turns,
            'winner' => $game->winner?->name,
            'is_tie' => $game->is_tie,
            'url' => route('games.summary', $game->uuid),
        ]);
    }
}
