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
use App\Models\Scheme;
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

        if (! $game->is_solo || $game->is_observable) {
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

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'killed'))->toOthers();
        }

        // Check for replaces_on_death (quick existence check before eager loading)
        $replacements = [];
        if ($gameCrewMember->character_id) {
            $hasReplacements = \DB::table('character_links')
                ->where('character_id', $gameCrewMember->character_id)
                ->where('type', 'replaces_on_death')
                ->exists();

            if ($hasReplacements) {
                /** @var Character $character */
                $character = Character::with('replacesOnDeath.miniatures')->findOrFail($gameCrewMember->character_id);
                /** @var Character $replacement */
                foreach ($character->replacesOnDeath as $replacement) {
                    $replacements[] = [
                        'id' => $replacement->id,
                        'display_name' => $replacement->display_name,
                        'count' => $replacement->pivot->count ?? 1,
                        'health' => $replacement->pivot->health ?? null,
                        'front_image' => ($firstMini = $replacement->miniatures->first())
                            ? '/storage/'.$firstMini->front_image
                            : null,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'replacements' => $replacements,
        ]);
    }

    public function reviveCrewMember(Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        $this->getMyPlayer($game);
        if ($gameCrewMember->game_id !== $game->id) {
            abort(403);
        }

        $gameCrewMember->update(['is_killed' => false, 'current_health' => $gameCrewMember->max_health]);

        if (! $game->is_solo || $game->is_observable) {
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
            'is_replacement' => ['sometimes', 'boolean'],
            'replacement_health' => ['nullable', 'integer', 'min:1'],
            'inherited_tokens' => ['nullable', 'array'],
            'inherited_upgrades' => ['nullable', 'array'],
            'is_activated' => ['sometimes', 'boolean'],
        ]);

        $character = Character::with('miniatures')->findOrFail($validated['character_id']);
        $isReplacement = ! empty($validated['is_replacement']);

        // Use selected miniature or fall back to first
        $miniature = isset($validated['miniature_id'])
            ? $character->miniatures->firstWhere('id', $validated['miniature_id']) ?? $character->miniatures->first()
            : $character->miniatures->first();

        // Enforce character count limit
        $existingCount = GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('character_id', $character->id)
            ->where('is_killed', false)
            ->count();

        $maxCount = $character->count ?? 1;
        if ($existingCount >= $maxCount) {
            return response()->json([
                'error' => "{$character->display_name} is at its limit ({$maxCount})",
                'at_limit' => true,
            ], 422);
        }

        $maxSort = GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->max('sort_order') ?? 0;

        // Determine tokens and upgrades: replacements inherit from killed member, summons get summon/slow
        $attachedTokens = [];
        $attachedUpgrades = [];
        if ($isReplacement) {
            $attachedTokens = $validated['inherited_tokens'] ?? [];
            $attachedUpgrades = $validated['inherited_upgrades'] ?? [];
        } else {
            $isPeon = $character->station?->value === 'peon';
            if (! $isPeon) {
                $tokens = \App\Models\Token::whereIn('slug', ['summon', 'slow'])->get(['id', 'name', 'slug']);
                foreach ($tokens as $token) {
                    $attachedTokens[] = ['id' => $token->id, 'name' => $token->name];
                }
            }
        }

        // Determine health: replacements use pivot health or default to 1
        $health = $isReplacement
            ? ($validated['replacement_health'] ?? 1)
            : $character->health;

        $member = GameCrewMember::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'character_id' => $character->id,
            'display_name' => $miniature ? $miniature->display_name : $character->display_name,
            'faction' => $character->getRawOriginal('faction'),
            'current_health' => $health,
            'max_health' => $character->health,
            'cost' => $character->cost ?? 0,
            'station' => $character->station?->value,
            'hiring_category' => $isReplacement ? 'replaced' : 'summoned',
            'front_image' => $miniature?->front_image,
            'back_image' => $miniature?->back_image,
            'is_summoned' => true,
            'is_activated' => ! empty($validated['is_activated']),
            'attached_upgrades' => $attachedUpgrades,
            'attached_tokens' => $attachedTokens,
            'attached_markers' => [],
            'sort_order' => $maxSort + 1,
        ]);

        if (! $game->is_solo || $game->is_observable) {
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

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'replaced'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function updateSchemeNotes(Request $request, Game $game): JsonResponse
    {
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        $validated = $request->validate([
            'scheme_notes' => ['required', 'array'],
            'scheme_notes.note' => ['nullable', 'string', 'max:500'],
            'scheme_notes.selected_model' => ['nullable', 'string', 'max:255'],
            'scheme_notes.selected_marker' => ['nullable', 'string', 'max:255'],
            'scheme_notes.terrain_note' => ['nullable', 'string', 'max:255'],
        ]);

        $player->update(['scheme_notes' => $validated['scheme_notes']]);

        return response()->json(['success' => true]);
    }

    public function updateSoulstonePool(Request $request, Game $game): JsonResponse
    {
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);
        $validated = $request->validate(['soulstone_pool' => ['required', 'integer', 'min:0']]);
        $player->update(['soulstone_pool' => $validated['soulstone_pool']]);

        if (! $game->is_solo || $game->is_observable) {
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
            'scheme_action' => ['required', 'string', 'in:scored,held,discarded'],
            'next_scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
            'next_scheme_notes' => ['nullable', 'array'],
            'next_scheme_notes.note' => ['nullable', 'string', 'max:500'],
            'next_scheme_notes.selected_model' => ['nullable', 'string', 'max:255'],
            'next_scheme_notes.selected_marker' => ['nullable', 'string', 'max:255'],
            'next_scheme_notes.terrain_note' => ['nullable', 'string', 'max:255'],
            // Solo: identify opponent's scheme this turn (scored or discarded)
            'identified_scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
        ]);

        // ── Validate scoring rules ──
        $previousTurns = GameTurn::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->get();

        if ($validated['strategy_points'] > 1) {
            $bonusUsed = $previousTurns->contains(fn (GameTurn $t) => $t->strategy_points > 1);
            if ($bonusUsed) {
                return response()->json(['error' => 'Strategy bonus already used this game'], 422);
            }
        }

        $totalSchemeScored = $previousTurns->sum('scheme_points');
        if ($validated['scheme_points'] > (6 - $totalSchemeScored)) {
            return response()->json(['error' => 'Scheme scoring would exceed 6 VP game maximum'], 422);
        }

        // ── Solo: identify opponent's scheme for this turn ──
        if ($game->is_solo && ! empty($validated['identified_scheme_id'])) {
            $playerPool = $player->scheme_pool ?? $game->scheme_pool ?? [];
            if (! in_array($validated['identified_scheme_id'], $playerPool)) {
                return response()->json(['error' => 'Scheme not in player pool'], 422);
            }
            $identifiedScheme = Scheme::find($validated['identified_scheme_id']);
            $newPool = $identifiedScheme ? array_values(array_filter([
                $identifiedScheme->next_scheme_one_id,
                $identifiedScheme->next_scheme_two_id,
                $identifiedScheme->next_scheme_three_id,
            ])) : [];
            $player->update([
                'current_scheme_id' => $validated['identified_scheme_id'],
                'scheme_pool' => ! empty($newPool) ? $newPool : ($player->scheme_pool ?? $game->scheme_pool ?? []),
            ]);
            $player->refresh();
        }

        // ── Validate next_scheme_id against player's pool ──
        $nextSchemeId = $validated['next_scheme_id'] ?? null;
        if ($nextSchemeId) {
            $playerPool = $player->scheme_pool ?? $game->scheme_pool ?? [];
            if (! in_array($nextSchemeId, $playerPool)) {
                return response()->json(['error' => 'Next scheme not in player pool'], 422);
            }
        }

        // ── Determine scheme action ──
        $schemeAction = $validated['scheme_action'];
        // Enforce: scored requires scheme_points > 0, held/discarded requires 0
        if ($schemeAction === 'scored' && $validated['scheme_points'] <= 0) {
            $schemeAction = 'held'; // Can't score with 0 points
        }
        if ($schemeAction !== 'scored' && $validated['scheme_points'] > 0) {
            $schemeAction = 'scored'; // If scoring points, it's scored
        }
        // Enforce: scored/discarded must have a next_scheme_id (unless last turn)
        if (in_array($schemeAction, ['scored', 'discarded']) && ! $nextSchemeId && $game->current_turn < $game->max_turns) {
            // Allow it but don't update — they'll need to pick next turn
        }

        $totalTurnPoints = $validated['strategy_points'] + $validated['scheme_points'];
        $crewSnapshot = $this->buildCrewSnapshot($game->id, $player->id);

        // ── Record the turn ──
        // For solo opponent held turns, scheme stays hidden — don't record the scheme_id
        $turnSchemeId = ($game->is_solo && $slot === 2 && $schemeAction === 'held')
            ? null
            : $player->current_scheme_id;

        GameTurn::updateOrCreate(
            [
                'game_id' => $game->id,
                'turn_number' => $game->current_turn,
                'game_player_id' => $player->id,
            ],
            [
                'scheme_id' => $turnSchemeId,
                'scheme_action' => $schemeAction,
                'scheme_notes' => $player->scheme_notes,
                'next_scheme_id' => $nextSchemeId,
                'strategy_points' => $validated['strategy_points'],
                'scheme_points' => $validated['scheme_points'],
                'points_scored' => $totalTurnPoints,
                'crew_snapshot' => $crewSnapshot,
            ]
        );

        $player->increment('total_points', $totalTurnPoints);

        // ── Update player scheme state for next turn ──
        if ($nextSchemeId) {
            // Player is switching: new scheme becomes current, derive new pool from it
            $nextScheme = Scheme::find($nextSchemeId);
            $newPool = $nextScheme ? array_values(array_filter([
                $nextScheme->next_scheme_one_id,
                $nextScheme->next_scheme_two_id,
                $nextScheme->next_scheme_three_id,
            ])) : [];

            $player->update([
                'current_scheme_id' => $nextSchemeId,
                'next_scheme_id' => null,
                'scheme_pool' => ! empty($newPool) ? $newPool : ($player->scheme_pool ?? $game->scheme_pool ?? []),
                'scheme_notes' => $validated['next_scheme_notes'] ?? null, // Set notes for new scheme
            ]);
        } elseif ($schemeAction === 'held') {
            // Held: scheme_pool stays the same, no changes needed
        }
        // For scored/discarded without next_scheme_id (last turn), no pool update needed

        // ── Turn completion + advancement ──
        $result = DB::transaction(function () use ($game, $player) {
            $player->update(['is_turn_complete' => true]);

            GameCrewMember::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->update(['is_activated' => false]);

            $bothDone = $game->players()->where('is_turn_complete', true)->count() === 2;
            if ($bothDone) {
                if ($game->current_turn >= $game->max_turns) {
                    $game->players()->update(['is_game_complete' => true]);
                    $this->finalizeGame($game);

                    return ['both_done' => true, 'game_complete' => true];
                }

                $game->increment('current_turn');
                $game->players()->update(['is_turn_complete' => false]);
            }

            return ['both_done' => $bothDone, 'game_complete' => false];
        });

        if ($result['game_complete']) {
            return response()->json(['success' => true, 'both_done' => true, 'game_complete' => true]);
        }

        if ($result['both_done']) {
            broadcast(new GameTurnAdvanced($game->fresh()))->toOthers();
        }

        broadcast(new GameCrewMemberUpdated($game, 'turn_scored'))->toOthers();

        return response()->json(['success' => true, 'both_done' => $result['both_done']]);
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

        $bothDone = DB::transaction(function () use ($game, $player) {
            $player->update(['is_game_complete' => true]);
            $bothDone = $game->players()->where('is_game_complete', true)->count() === 2;
            if ($bothDone) {
                $this->finalizeGame($game);
            }

            return $bothDone;
        });

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'mark_complete'))->toOthers();
        }

        return response()->json(['success' => true, 'game_complete' => $bothDone]);
    }

    public function cancelComplete(Game $game): JsonResponse
    {
        $player = $this->getMyPlayer($game);

        if ($game->status !== GameStatusEnum::InProgress) {
            return response()->json(['error' => 'Game is not in progress'], 422);
        }

        // Can only cancel if the game hasn't been finalized yet (both haven't agreed)
        $bothDone = $game->players()->where('is_game_complete', true)->count() === 2;
        if ($bothDone) {
            return response()->json(['error' => 'Game already finalized'], 422);
        }

        $player->update(['is_game_complete' => false]);

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'cancel_complete'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    private function finalizeGame(Game $game): void
    {
        // Ensure every player has a turn record for the current turn (snapshot crew state)
        $players = $game->players()->get();
        foreach ($players as $player) {
            /** @var GamePlayer $player */
            $existingTurn = GameTurn::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('turn_number', $game->current_turn)
                ->first();

            if (! $existingTurn) {
                // Create a turn record with 0 points to capture the crew snapshot
                $crewSnapshot = GameCrewMember::where('game_id', $game->id)
                    ->where('game_player_id', $player->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (GameCrewMember $m) => [
                        'id' => $m->id,
                        'character_id' => $m->character_id,
                        'display_name' => $m->display_name,
                        'faction' => $m->getRawOriginal('faction'),
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

                GameTurn::create([
                    'game_id' => $game->id,
                    'turn_number' => $game->current_turn,
                    'game_player_id' => $player->id,
                    'scheme_id' => $player->current_scheme_id,
                    'strategy_points' => 0,
                    'scheme_points' => 0,
                    'points_scored' => 0,
                    'crew_snapshot' => $crewSnapshot,
                ]);
            }
        }

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

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameStatusChanged($game));
        }
    }

    private function buildCrewSnapshot(int $gameId, int $playerId): array
    {
        return GameCrewMember::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (GameCrewMember $m) => [
                'id' => $m->id,
                'character_id' => $m->character_id,
                'display_name' => $m->display_name,
                'faction' => $m->getRawOriginal('faction'),
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
