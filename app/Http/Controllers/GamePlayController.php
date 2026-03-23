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
            'display_name' => ['sometimes', 'string', 'max:255'],
            'front_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'back_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // Validate upgrade plentiful limits
        if (isset($validated['attached_upgrades']) && ! empty($validated['attached_upgrades'])) {
            $newUpgradeIds = collect($validated['attached_upgrades'])->pluck('id')->filter()->toArray();
            if ($newUpgradeIds) {
                $upgrades = \App\Models\Upgrade::whereIn('id', $newUpgradeIds)->get()->keyBy('id');
                // Count how many times each upgrade is used across all OTHER crew members
                $allMembers = GameCrewMember::where('game_id', $game->id)
                    ->where('game_player_id', $gameCrewMember->game_player_id)
                    ->where('id', '!=', $gameCrewMember->id)
                    ->get();

                foreach ($newUpgradeIds as $upgradeId) {
                    $upgrade = $upgrades->get($upgradeId);
                    if (! $upgrade) {
                        continue;
                    }
                    $plentiful = $upgrade->plentiful ?? 1;
                    $usedCount = $allMembers->filter(fn (GameCrewMember $m) => collect($m->attached_upgrades ?? [])->contains('id', $upgradeId))->count();
                    if ($usedCount >= $plentiful) {
                        return response()->json([
                            'error' => "{$upgrade->name} is at its limit ({$plentiful})",
                        ], 422);
                    }
                }
            }
        }

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
            'miniature_id' => ['nullable', 'integer', 'exists:miniatures,id'],
        ]);

        $character = Character::with('miniatures')->findOrFail($validated['character_id']);

        // Use selected miniature or fall back to first
        $miniature = isset($validated['miniature_id'])
            ? $character->miniatures->firstWhere('id', $validated['miniature_id']) ?? $character->miniatures->first()
            : $character->miniatures->first();

        // Enforce character count limit across the crew
        $existingCount = GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('character_id', $character->id)
            ->where('is_killed', false)
            ->count();

        $maxCount = $character->count ?? 1;
        if ($existingCount >= $maxCount) {
            return response()->json([
                'error' => "{$character->display_name} is at its limit ({$maxCount})",
            ], 422);
        }

        $maxSort = GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->max('sort_order') ?? 0;

        // Peons don't get Summon/Slow tokens; all others do
        $summonTokens = [];
        $isPeon = $character->station?->value === 'peon';
        if (! $isPeon) {
            $tokens = \App\Models\Token::whereIn('slug', ['summon', 'slow'])->get(['id', 'name', 'slug']);
            foreach ($tokens as $token) {
                $summonTokens[] = ['id' => $token->id, 'name' => $token->name];
            }
        }

        $member = GameCrewMember::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'character_id' => $character->id,
            'display_name' => $miniature ? $miniature->display_name : $character->display_name,
            'faction' => $character->getRawOriginal('faction'),
            'current_health' => $character->health,
            'max_health' => $character->health,
            'cost' => $character->cost ?? 0,
            'station' => $character->station?->value,
            'hiring_category' => 'summoned',
            'front_image' => $miniature?->front_image,
            'back_image' => $miniature?->back_image,
            'is_summoned' => true,
            'attached_upgrades' => [],
            'attached_tokens' => $summonTokens,
            'attached_markers' => [],
            'sort_order' => $maxSort + 1,
        ]);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'summoned'))->toOthers();
        }

        return response()->json(['success' => true, 'member_id' => $member->id]);
    }

    public function replaceCrewMember(Request $request, Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        // Verify the crew member belongs to this game
        if ($gameCrewMember->game_id !== $game->id) {
            abort(403);
        }

        $validated = $request->validate([
            'character_id' => ['required', 'exists:characters,id'],
            'miniature_id' => ['nullable', 'integer', 'exists:miniatures,id'],
        ]);

        $character = Character::with('miniatures')->findOrFail($validated['character_id']);

        // Use selected miniature or fall back to first
        $miniature = isset($validated['miniature_id'])
            ? $character->miniatures->firstWhere('id', $validated['miniature_id']) ?? $character->miniatures->first()
            : $character->miniatures->first();

        // Transfer health: use old member's current health, capped at new character's max
        $newHealth = min($gameCrewMember->current_health, $character->health);

        // Update the existing crew member in-place
        $gameCrewMember->update([
            'character_id' => $character->id,
            'display_name' => $miniature ? $miniature->display_name : $character->display_name,
            'faction' => $character->getRawOriginal('faction'),
            'current_health' => $newHealth,
            'max_health' => $character->health,
            'cost' => $gameCrewMember->cost, // Preserve original hiring cost for budget tracking
            'station' => $character->station?->value,
            'front_image' => $miniature?->front_image,
            'back_image' => $miniature?->back_image,
            'attached_upgrades' => [],
            'attached_tokens' => [],
            'attached_markers' => [],
        ]);

        if (! $game->is_solo) {
            broadcast(new GameCrewMemberUpdated($game, 'replaced'))->toOthers();
        }

        return response()->json(['success' => true]);
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
            'strategy_points' => ['required', 'integer', 'min:0', 'max:2'],
            'scheme_points' => ['required', 'integer', 'min:0', 'max:2'],
            'next_scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
        ]);

        // Validate scoring rules against previous turns
        $previousTurns = GameTurn::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->get();

        // Strategy: max 1/turn + 1 bonus once per game (max 2 any single turn)
        if ($validated['strategy_points'] > 1) {
            $bonusUsed = $previousTurns->contains(fn (GameTurn $t) => $t->strategy_points > 1);
            if ($bonusUsed) {
                return response()->json(['error' => 'Strategy bonus already used this game'], 422);
            }
        }

        // Scheme: max 2/turn, max 6 total across the game
        $totalSchemeScored = $previousTurns->sum('scheme_points');
        $schemeRemaining = 6 - $totalSchemeScored;
        if ($validated['scheme_points'] > $schemeRemaining) {
            return response()->json(['error' => 'Scheme scoring would exceed 6 VP game maximum'], 422);
        }

        $totalTurnPoints = $validated['strategy_points'] + $validated['scheme_points'];

        // Snapshot crew state at end of turn
        $crewSnapshot = GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (GameCrewMember $m) => [
                'id' => $m->id,
                'character_id' => $m->character_id,
                'display_name' => $m->display_name,
                'faction' => $m->faction,
                'current_health' => $m->current_health,
                'max_health' => $m->max_health,
                'is_killed' => $m->is_killed,
                'is_summoned' => $m->is_summoned,
                'is_activated' => $m->is_activated,
                'attached_tokens' => $m->attached_tokens ?? [],
                'attached_upgrades' => $m->attached_upgrades ?? [],
                'hiring_category' => $m->hiring_category,
                'cost' => $m->cost,
            ])
            ->toArray();

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
                'crew_snapshot' => $crewSnapshot,
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
            // If this was the last turn, auto-complete the game
            if ($game->current_turn >= $game->max_turns) {
                $game->players()->update(['is_game_complete' => true]);
                $this->finalizeGame($game);

                return response()->json(['success' => true, 'both_done' => true, 'game_complete' => true]);
            }

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
