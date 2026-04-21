<?php

namespace App\Http\Controllers;

use App\Enums\GameStatusEnum;
use App\Enums\TournamentGameResultEnum;
use App\Events\GameCrewMemberUpdated;
use App\Events\GameStatusChanged;
use App\Events\GameTurnAdvanced;
use App\Events\TournamentUpdated;
use App\Http\Requests\Games\ReplaceCrewMemberRequest;
use App\Http\Requests\Games\SubmitTurnRequest;
use App\Http\Requests\Games\SummonCrewMemberRequest;
use App\Http\Requests\Games\SwapCrewUpgradeRequest;
use App\Http\Requests\Games\UpdateCrewMemberRequest;
use App\Http\Requests\Games\UpdateSchemeNotesRequest;
use App\Http\Requests\Games\UpdateSoulstonePoolRequest;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Scheme;
use App\Models\TournamentGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GamePlayController extends Controller
{
    private function assertInProgress(Game $game): void
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            abort(422, 'Game not in progress');
        }
    }

    public function updateCrewMember(UpdateCrewMemberRequest $request, Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        $this->assertInProgress($game);

        $validated = $request->validated();

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
        $this->assertInProgress($game);
        $this->authorize('updateCrewMember', [$game, $gameCrewMember]);

        // Idempotent: if already killed, skip DB write + broadcast but
        // still return replacements so the UI can handle the state.
        if (! $gameCrewMember->is_killed) {
            $gameCrewMember->update(['is_killed' => true, 'current_health' => 0]);
        }

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
        $this->assertInProgress($game);
        $this->authorize('updateCrewMember', [$game, $gameCrewMember]);

        // Idempotent: skip if already alive.
        if (! $gameCrewMember->is_killed) {
            return response()->json(['success' => true]);
        }

        $gameCrewMember->update(['is_killed' => false, 'current_health' => $gameCrewMember->max_health]);

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'revived'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function summonCrewMember(SummonCrewMemberRequest $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        $validated = $request->validated();

        $character = Character::with('miniatures')->findOrFail($validated['character_id']);
        $isReplacement = ! empty($validated['is_replacement']);
        $maxCount = $character->count ?? 1;

        // Use selected miniature or fall back to first
        $miniature = isset($validated['miniature_id'])
            ? $character->miniatures->firstWhere('id', $validated['miniature_id']) ?? $character->miniatures->first()
            : $character->miniatures->first();

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

        // Enforce character count limit and create the member atomically. The
        // lockForUpdate + count check + create must share one transaction so
        // two concurrent summons can't both pass the count check.
        $result = DB::transaction(function () use ($game, $player, $character, $miniature, $maxCount, $isReplacement, $health, $attachedTokens, $attachedUpgrades, $validated) {
            GameCrewMember::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->lockForUpdate()
                ->get();

            $liveCount = GameCrewMember::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('character_id', $character->id)
                ->where('is_killed', false)
                ->count();

            if ($liveCount >= $maxCount) {
                return ['at_limit' => true];
            }

            $maxSort = GameCrewMember::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->max('sort_order') ?? 0;

            $member = GameCrewMember::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'character_id' => $character->id,
                'display_name' => $miniature ? $miniature->display_name : $character->display_name,
                'faction' => $character->getRawOriginal('faction'),
                'current_health' => $health,
                'max_health' => $character->health,
                'defense' => $character->defense,
                'willpower' => $character->willpower,
                'speed' => $character->speed,
                'size' => $character->size,
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

            return ['member' => $member];
        });

        if (! empty($result['at_limit'])) {
            return response()->json([
                'error' => "{$character->display_name} is at its limit ({$maxCount})",
                'at_limit' => true,
            ], 422);
        }

        $member = $result['member'];

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'summoned'))->toOthers();
        }

        return response()->json(['success' => true, 'member_id' => $member->id]);
    }

    public function replaceCrewMember(ReplaceCrewMemberRequest $request, Game $game, GameCrewMember $gameCrewMember): RedirectResponse
    {
        $this->assertInProgress($game);

        $validated = $request->validated();

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
            'defense' => $character->defense,
            'willpower' => $character->willpower,
            'speed' => $character->speed,
            'size' => $character->size,
            'cost' => $gameCrewMember->cost, // Preserve original hiring cost for budget tracking
            'station' => $character->station?->value,
            'front_image' => $miniature?->front_image,
            'back_image' => $miniature?->back_image,
            'attached_upgrades' => $gameCrewMember->attached_upgrades ?? [],
            'attached_tokens' => $gameCrewMember->attached_tokens ?? [],
            'attached_markers' => [],
        ]);

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'replaced'))->toOthers();
        }

        return back();
    }

    public function updateSchemeNotes(UpdateSchemeNotesRequest $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        $validated = $request->validated();

        $player->update(['scheme_notes' => $validated['scheme_notes']]);

        return response()->json(['success' => true]);
    }

    public function swapCrewUpgrade(SwapCrewUpgradeRequest $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        $validated = $request->validated();

        // Verify the upgrade belongs to the player's master's crew upgrades
        $master = $player->master;
        if (! $master || $master->crew_upgrade_mode !== \App\Enums\CrewUpgradeModeEnum::Swappable) {
            return response()->json(['error' => 'Crew upgrades are not swappable for this master'], 422);
        }

        $validIds = $master->crewUpgrades->pluck('id')->toArray();
        if (! in_array($validated['active_crew_upgrade_id'], $validIds)) {
            return response()->json(['error' => 'Upgrade not available for this master'], 422);
        }

        $player->update(['active_crew_upgrade_id' => $validated['active_crew_upgrade_id']]);

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'updated'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function updateSoulstonePool(UpdateSoulstonePoolRequest $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);
        $validated = $request->validated();
        $player->update(['soulstone_pool' => $validated['soulstone_pool']]);

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'soulstone_pool'))->toOthers();
        }

        return response()->json(['success' => true]);
    }

    public function submitTurnScore(SubmitTurnRequest $request, Game $game): JsonResponse
    {
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        if ($game->status !== GameStatusEnum::InProgress) {
            return response()->json(['error' => 'Game not in progress'], 422);
        }

        $validated = $request->validated();

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

        // For solo opponent held turns, scheme stays hidden — don't record the scheme_id
        $turnSchemeId = ($game->is_solo && $slot === 2 && $schemeAction === 'held')
            ? null
            : $player->current_scheme_id;

        // All writes below run in one transaction with a game-row lock so that
        // a double-submit can't double-advance the turn counter or inflate
        // total_points. GameTurn::updateOrCreate + recompute-from-sum keeps
        // the scoring side idempotent on retry.
        $result = DB::transaction(function () use ($game, $player, $turnSchemeId, $schemeAction, $validated, $nextSchemeId, $totalTurnPoints, $crewSnapshot) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);

            if ($locked->status !== GameStatusEnum::InProgress) {
                return ['already_finalized' => true];
            }

            GameTurn::updateOrCreate(
                [
                    'game_id' => $locked->id,
                    'turn_number' => $locked->current_turn,
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

            $player->update([
                'total_points' => GameTurn::where('game_id', $locked->id)
                    ->where('game_player_id', $player->id)
                    ->sum('points_scored'),
            ]);

            if ($nextSchemeId) {
                $nextScheme = Scheme::find($nextSchemeId);
                $newPool = $nextScheme ? array_values(array_filter([
                    $nextScheme->next_scheme_one_id,
                    $nextScheme->next_scheme_two_id,
                    $nextScheme->next_scheme_three_id,
                ])) : [];

                $player->update([
                    'current_scheme_id' => $nextSchemeId,
                    'next_scheme_id' => null,
                    'scheme_pool' => ! empty($newPool) ? $newPool : ($player->scheme_pool ?? $locked->scheme_pool ?? []),
                    'scheme_notes' => $validated['next_scheme_notes'] ?? null,
                ]);
            }

            $player->update(['is_turn_complete' => true]);

            GameCrewMember::where('game_id', $locked->id)
                ->where('game_player_id', $player->id)
                ->update(['is_activated' => false]);

            $bothDone = $locked->players()->where('is_turn_complete', true)->count() === 2;
            if ($bothDone) {
                if ($locked->current_turn >= $locked->max_turns) {
                    $locked->players()->update(['is_game_complete' => true]);
                    $this->finalizeGame($locked);

                    return ['both_done' => true, 'game_complete' => true];
                }

                $locked->increment('current_turn');
                $locked->players()->update(['is_turn_complete' => false]);
            }

            return ['both_done' => $bothDone, 'game_complete' => false];
        });

        if (! empty($result['already_finalized'])) {
            return response()->json(['success' => true, 'both_done' => true, 'game_complete' => true]);
        }

        // Push the fresh per-player VP to a linked TournamentGame (if any). Runs
        // after the transaction so a rollback can't leave the tournament ahead
        // of the tracker.
        $this->syncToTournamentGame($game->fresh()->load('players.master'));

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
            $this->syncToTournamentGame($game->fresh()->load('players.master'));

            return response()->json(['success' => true, 'game_complete' => true]);
        }

        $bothDone = DB::transaction(function () use ($game, $player) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);
            $player->update(['is_game_complete' => true]);
            $done = $locked->players()->where('is_game_complete', true)->count() === 2;
            if ($done) {
                $this->finalizeGame($locked);
            }

            return $done;
        });

        if ($bothDone) {
            $this->syncToTournamentGame($game->fresh()->load('players.master'));
        }

        // Solo games already returned above — this is always multiplayer.
        broadcast(new GameCrewMemberUpdated($game, 'mark_complete'))->toOthers();

        return response()->json(['success' => true, 'game_complete' => $bothDone]);
    }

    public function cancelComplete(Game $game): RedirectResponse
    {
        $player = $this->getMyPlayer($game);

        if ($game->status !== GameStatusEnum::InProgress) {
            abort(422, 'Game is not in progress');
        }

        // Can only cancel if the game hasn't been finalized yet (both haven't agreed)
        $bothDone = $game->players()->where('is_game_complete', true)->count() === 2;
        if ($bothDone) {
            abort(422, 'Game already finalized');
        }

        $player->update(['is_game_complete' => false]);

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameCrewMemberUpdated($game, 'cancel_complete'))->toOthers();
        }

        return back();
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
                // Reuse the shared buildCrewSnapshot helper.
                $crewSnapshot = $this->buildCrewSnapshot($game->id, $player->id);
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

    /**
     * Build a JSON-serializable snapshot of all crew members for a player.
     * Used both per-turn (inside submitTurnScore) and on finalize (to record
     * the crew state at game completion).
     */
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
                'defense' => $m->defense,
                'willpower' => $m->willpower,
                'speed' => $m->speed,
                'size' => $m->size,
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

    /**
     * Flow current per-player VP from a tracker game into its linked TournamentGame.
     *
     * Keeps the tournament's recorded score in sync with the live tracker state —
     * the TO still confirms the final result (TournamentGame.result stays Pending
     * here), but they see the actual score as it develops rather than having to
     * re-enter it at the end.
     *
     * Skips if the TournamentGame is already Completed or Forfeited — the TO has
     * confirmed / intervened and we don't want to clobber that.
     */
    private function syncToTournamentGame(Game $game): void
    {
        /** @var TournamentGame|null $tg */
        $tg = TournamentGame::with('round.tournament')->where('game_id', $game->id)->first();
        if (! $tg) {
            return;
        }
        if (in_array($tg->result, [TournamentGameResultEnum::Completed, TournamentGameResultEnum::Forfeited])) {
            return;
        }

        /** @var GamePlayer|null $p1 */
        $p1 = $game->players->firstWhere('slot', 1) ?? $game->players()->where('slot', 1)->first();
        /** @var GamePlayer|null $p2 */
        $p2 = $game->players->firstWhere('slot', 2) ?? $game->players()->where('slot', 2)->first();
        if (! $p1 || ! $p2) {
            return;
        }

        $turns = GameTurn::where('game_id', $game->id)
            ->whereIn('game_player_id', [$p1->id, $p2->id])
            ->get()
            ->groupBy('game_player_id');
        $p1Turns = $turns->get($p1->id, collect());
        $p2Turns = $turns->get($p2->id, collect());

        $tg->update([
            'player_one_strategy_vp' => (int) $p1Turns->sum('strategy_points'),
            'player_one_scheme_vp' => (int) $p1Turns->sum('scheme_points'),
            'player_one_vp' => (int) $p1->total_points,
            'player_two_strategy_vp' => (int) $p2Turns->sum('strategy_points'),
            'player_two_scheme_vp' => (int) $p2Turns->sum('scheme_points'),
            'player_two_vp' => (int) $p2->total_points,
            // Only back-fill master/title/faction when the TO hasn't entered them —
            // preserves manual corrections (typo fixes, alt title pick, etc.).
            //
            // The TO score dialog persists `title` as the full display_name
            // (e.g. "Nellie with a Past") so it round-trips through the Select
            // options keyed on display_name. Writing just Character.title
            // (the bare suffix) here left the Title dropdown blank on reopen.
            'player_one_master' => $tg->player_one_master ?: $p1->master?->name,
            'player_one_title' => $tg->player_one_title ?: $p1->master?->display_name,
            'player_one_faction' => $tg->player_one_faction ?: $p1->getRawOriginal('faction'),
            'player_two_master' => $tg->player_two_master ?: $p2->master?->name,
            'player_two_title' => $tg->player_two_title ?: $p2->master?->display_name,
            'player_two_faction' => $tg->player_two_faction ?: $p2->getRawOriginal('faction'),
            'player_one_crew_build_id' => $tg->player_one_crew_build_id ?: $p1->crew_build_id,
            'player_two_crew_build_id' => $tg->player_two_crew_build_id ?: $p2->crew_build_id,
        ]);

        // Broadcast so any open Manage/View page refreshes live.
        if ($tg->round && $tg->round->tournament) {
            broadcast(new TournamentUpdated($tg->round->tournament, 'tracker_synced'))->toOthers();
        }
    }
}
