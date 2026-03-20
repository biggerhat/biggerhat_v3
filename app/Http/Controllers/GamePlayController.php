<?php

namespace App\Http\Controllers;

use App\Enums\GameStatusEnum;
use App\Events\GameCrewMemberUpdated;
use App\Events\GameStatusChanged;
use App\Events\GameTurnAdvanced;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GamePlayController extends Controller
{
    public function updateCrewMember(Request $request, Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        $player = $this->getMyPlayer($game);
        if (! $game->is_solo) {
            $this->assertOwnsCrewMember($player, $gameCrewMember);
        } elseif ($gameCrewMember->game_id !== $game->id) {
            abort(403);
        }

        $validated = $request->validate([
            'current_health' => ['sometimes', 'integer', 'min:0'],
            'is_activated' => ['sometimes', 'boolean'],
            'attached_tokens' => ['sometimes', 'array'],
            'attached_markers' => ['sometimes', 'array'],
            'attached_upgrades' => ['sometimes', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $gameCrewMember->update($validated);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'updated'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function killCrewMember(Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        $this->getMyPlayer($game);
        if ($gameCrewMember->game_id !== $game->id) {
            abort(403);
        }

        $gameCrewMember->update(['is_killed' => true, 'current_health' => 0]);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'killed'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function reviveCrewMember(Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        $this->getMyPlayer($game);
        if ($gameCrewMember->game_id !== $game->id) {
            abort(403);
        }

        $gameCrewMember->update(['is_killed' => false, 'current_health' => $gameCrewMember->max_health]);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'revived'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function summonCrewMember(Request $request, Game $game): JsonResponse
    {
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        $validated = $request->validate([
            'character_id' => ['required', 'exists:characters,id'],
        ]);

        $character = Character::with('miniatures')->findOrFail($validated['character_id']);

        $maxSort = GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->max('sort_order') ?? 0;

        $member = GameCrewMember::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'character_id' => $character->id,
            'display_name' => $character->display_name,
            'current_health' => $character->health,
            'max_health' => $character->health,
            'cost' => $character->cost ?? 0,
            'station' => $character->station?->value,
            'hiring_category' => 'summoned',
            'front_image' => $character->miniatures->first()?->front_image,
            'back_image' => $character->miniatures->first()?->back_image,
            'is_summoned' => true,
            'attached_upgrades' => [],
            'attached_tokens' => [],
            'attached_markers' => [],
            'sort_order' => $maxSort + 1,
        ]);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'summoned'))->toOthers();
        }

        return response()->json(['success' => true, 'member_id' => $member->id]);
    }

    public function updateSoulstonePool(Request $request, Game $game): JsonResponse
    {
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);
        $validated = $request->validate(['soulstone_pool' => ['required', 'integer', 'min:0']]);
        $player->update(['soulstone_pool' => $validated['soulstone_pool']]);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'soulstone_pool'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function submitTurnScore(Request $request, Game $game): JsonResponse
    {
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        if ($game->status !== GameStatusEnum::InProgress) {
            return response()->json(['error' => 'Game not in progress'], 422);
        }

        $validated = $request->validate([
            'strategy_points' => ['required', 'integer', 'min:0', 'max:5'],
            'scheme_points' => ['required', 'integer', 'min:0', 'max:5'],
            'next_scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
        ]);

        $totalTurnPoints = $validated['strategy_points'] + $validated['scheme_points'];

        // Record the turn
        GameTurn::updateOrCreate(
            [
                'game_id' => $game->id,
                'turn_number' => $game->current_turn,
                'game_player_id' => $player->id,
            ],
            [
                'scheme_id' => $player->current_scheme_id,
                'strategy_points' => $validated['strategy_points'],
                'scheme_points' => $validated['scheme_points'],
                'points_scored' => $totalTurnPoints,
            ]
        );

        // Update total points
        $player->increment('total_points', $totalTurnPoints);

        // Update scheme for next turn if changed
        if ($validated['next_scheme_id']) {
            $player->update(['current_scheme_id' => $validated['next_scheme_id']]);
        }

        $player->update(['is_turn_complete' => true]);

        // Reset activation state for all crew members when turn completes
        GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->update(['is_activated' => false]);

        // Check if both players are done
        $bothDone = $game->players()->where('is_turn_complete', true)->count() === 2;
        if ($bothDone) {
            DB::transaction(function () use ($game) {
                $game->increment('current_turn');
                $game->players()->update(['is_turn_complete' => false]);
            });
            if (! $game->is_solo) {
                broadcast(new GameTurnAdvanced($game->fresh()))->toOthers();
            }
        }

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'turn_scored'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function markComplete(Game $game): JsonResponse
    {
        $player = $this->getMyPlayer($game);

        if ($game->is_solo) {
            // Solo: complete both players at once
            $game->players()->update(['is_game_complete' => true]);
            $this->finalizeGame($game);

            return response()->json(['success' => true, 'game_complete' => true]);
        }

        $player->update(['is_game_complete' => true]);

        $bothDone = $game->players()->where('is_game_complete', true)->count() === 2;
        if ($bothDone) {
            $this->finalizeGame($game);
        }

        broadcast(new GameCrewMemberUpdated($game, 'mark_complete'))->toOthers();

        return response()->json(['success' => true, 'game_complete' => $bothDone]);
    }

    private function finalizeGame(Game $game): void
    {
        $players = $game->players()->get();
        /** @var GamePlayer $p1 */
        $p1 = $players->first();
        /** @var GamePlayer $p2 */
        $p2 = $players->last();

        if ($p1->total_points === $p2->total_points) {
            $game->update([
                'status' => GameStatusEnum::Completed,
                'completed_at' => now(),
                'is_tie' => true,
                'winner_id' => null,
                'winner_slot' => null,
            ]);
        } else {
            $winner = $p1->total_points > $p2->total_points ? $p1 : $p2;
            $game->update([
                'status' => GameStatusEnum::Completed,
                'completed_at' => now(),
                'winner_id' => $winner->user_id, // null for solo opponent
                'winner_slot' => $winner->slot,
            ]);
        }

        if (! $game->is_solo) {
            broadcast(new GameStatusChanged($game));
        }
    }

    private function getMyPlayer(Game $game): GamePlayer
    {
        /** @var GamePlayer|null $player */
        $player = $game->players()->where('user_id', Auth::id())->first();
        if (! $player) {
            abort(403);
        }

        return $player;
    }

    private function getPlayerForSlot(Game $game, int $slot): GamePlayer
    {
        if ($game->creator_id !== Auth::id()) {
            abort(403, 'Not the solo game owner');
        }
        /** @var GamePlayer $player */
        $player = $game->players()->where('slot', $slot)->firstOrFail();

        return $player;
    }

    private function assertOwnsCrewMember(GamePlayer $player, GameCrewMember $member): void
    {
        if ($member->game_player_id !== $player->id) {
            abort(403, 'Not your crew member');
        }
    }
}
