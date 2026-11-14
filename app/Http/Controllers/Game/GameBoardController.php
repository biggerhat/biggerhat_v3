<?php

namespace App\Http\Controllers\Game;

use App\Enums\BaseSizeEnum;
use App\Events\GameBoardMoved;
use App\Http\Controllers\Controller;
use App\Http\Requests\Games\UpdateBoardRequest;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Pennant\Feature;

class GameBoardController extends Controller
{
    public function show(Game $game): Response|ResponseFactory
    {
        $this->guard();
        Gate::authorize('view', $game);

        $game->load(['players.user:id,name', 'players.crewMembers.character:id,base', 'players.crewMembers.customCharacter:id,base']);

        return inertia('Games/Board', [
            'game' => ['uuid' => $game->uuid, 'is_solo' => (bool) $game->is_solo, 'deployment' => $game->deployment?->value],
            'players' => $game->players->map(fn (GamePlayer $p) => [
                'slot' => $p->slot,
                'name' => $this->playerName($p),
                'mine' => $this->controls($p, $game),
            ])->values(),
            'models' => $game->players->flatMap(fn (GamePlayer $p) => $p->crewMembers->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'slot' => $p->slot,
                'base_mm' => $this->baseMm($m),
                'killed' => (bool) $m->is_killed,
            ]))->values(),
            'positions' => $game->board_state['positions'] ?? (object) [],
        ]);
    }

    public function update(UpdateBoardRequest $request, Game $game): RedirectResponse
    {
        $this->guard();

        $game->load('players.crewMembers:id,game_player_id');
        $movable = $game->players
            ->filter(fn (GamePlayer $p) => $this->controls($p, $game))
            ->flatMap(fn (GamePlayer $p) => $p->crewMembers->pluck('id'))
            ->map(fn ($id) => (string) $id)
            ->all();

        $incoming = collect($request->validated('positions'))
            ->filter(fn ($_, $id) => in_array((string) $id, $movable, true))
            ->map(fn (array $p) => ['x' => round((float) $p['x'], 2), 'y' => round((float) $p['y'], 2)]);

        abort_if($incoming->isEmpty(), 403, 'You can only move your own models.');

        // Merge under a lock so two players dropping models at once do not overwrite each other.
        DB::transaction(function () use ($game, $incoming) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $positions = array_replace($locked->board_state['positions'] ?? [], $incoming->all());
            $locked->update(['board_state' => ['positions' => $positions]]);
        });

        broadcast(new GameBoardMoved($game, $incoming->all()))->toOthers();

        return redirect()->back();
    }

    /** Pre-release: hidden (404, not 403) until the flag is on. */
    private function guard(): void
    {
        abort_unless(Feature::for(null)->active('experiment-tabletop'), 404);
    }

    /** Base size in mm from the character card, or the custom card, falling back to 30 mm (the common size). */
    private function baseMm(GameCrewMember $member): int
    {
        if ($member->character_id !== null) {
            return $member->character->base->value;
        }
        if ($member->custom_character_id !== null) {
            return $member->customCharacter->base->value;
        }

        return BaseSizeEnum::ThirtyMM->value;
    }

    /** You move your own crew. In a solo game you play both sides. */
    private function controls(GamePlayer $player, Game $game): bool
    {
        return $game->is_solo ? true : $player->user_id === auth()->id();
    }

    private function playerName(GamePlayer $player): string
    {
        return ($player->user_id === null ? null : $player->user->name) ?? $player->opponent_name ?? 'Opponent';
    }
}
