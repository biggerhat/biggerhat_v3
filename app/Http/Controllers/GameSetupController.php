<?php

namespace App\Http\Controllers;

use App\Enums\CharacterStationEnum;
use App\Enums\GameStatusEnum;
use App\Events\GameSetupStepCompleted;
use App\Events\GameStatusChanged;
use App\Http\Requests\Games\SubmitCrewRequest;
use App\Http\Requests\Games\SubmitFactionRequest;
use App\Http\Requests\Games\SubmitMasterRequest;
use App\Http\Requests\Games\SubmitSchemeRequest;
use App\Http\Requests\Games\UpdateOpponentNameRequest;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Scheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameSetupController extends Controller
{
    public function submitFaction(SubmitFactionRequest $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        if ($game->status !== GameStatusEnum::FactionSelect) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validated();

        $player->update(['faction' => $validated['faction']]);

        // Pessimistic lock: prevent two concurrent submissions from both
        // seeing "2 of 2 done" and advancing the status twice.
        $bothDone = DB::transaction(function () use ($game) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);
            $done = $locked->players()->whereNotNull('faction')->count() === 2;
            if ($done && $locked->status === GameStatusEnum::FactionSelect) {
                $locked->update(['status' => GameStatusEnum::MasterSelect]);
            }

            return $done;
        });

        if (! $game->is_solo) {
            if ($bothDone) {
                broadcast(new GameStatusChanged($game))->toOthers();
            }
            broadcast(new GameSetupStepCompleted($game, $player, 'faction'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function submitMaster(SubmitMasterRequest $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        // Allow changing title during crew_select (before crew is locked in)
        if (! in_array($game->status, [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect])) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validated();

        // Find master character by display_name or name + faction
        $master = Character::standard()->where('station', CharacterStationEnum::Master->value)
            ->where(function ($q) use ($player) {
                $q->where('faction', $player->faction)
                    ->orWhere('second_faction', $player->faction);
            })
            ->where(function ($q) use ($validated) {
                $q->where('display_name', $validated['master_name'])
                    ->orWhere('name', $validated['master_name']);
            })
            ->first();

        $player->update([
            'master_name' => $validated['master_name'],
            'master_id' => $master?->id,
        ]);

        [$bothDone, $statusChanged] = DB::transaction(function () use ($game) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);
            $done = $locked->players()->whereNotNull('master_name')->count() === 2;
            $changed = false;
            if ($done && $locked->status === GameStatusEnum::MasterSelect) {
                $locked->update(['status' => GameStatusEnum::CrewSelect]);
                $changed = true;
            }

            return [$done, $changed];
        });

        if (! $game->is_solo) {
            if ($statusChanged) {
                broadcast(new GameStatusChanged($game))->toOthers();
            }
            broadcast(new GameSetupStepCompleted($game, $player, 'master'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function submitCrew(SubmitCrewRequest $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        if ($game->status !== GameStatusEnum::CrewSelect) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validated();

        $crewBuild = CrewBuild::findOrFail($validated['crew_build_id']);

        // Verify ownership (solo mode can use any of the user's crews for either slot)
        if ($crewBuild->user_id !== Auth::id()) {
            return response()->json(['error' => 'Not your crew'], 403);
        }

        DB::transaction(function () use ($player, $game, $crewBuild) {
            // Update crew selection and lock in the master title from the crew build
            $crewMaster = $crewBuild->master;
            // For swappable masters, default to first crew upgrade; for select_one, use the build's selection
            $activeUpgradeId = $crewBuild->crew_upgrade_id;
            if ($crewMaster && $crewMaster->crew_upgrade_mode === \App\Enums\CrewUpgradeModeEnum::Swappable) {
                /** @var \App\Models\Upgrade|null $firstUpgrade */
                $firstUpgrade = $crewMaster->crewUpgrades()->first();
                $activeUpgradeId = $activeUpgradeId ?? $firstUpgrade?->id;
            }

            $player->update([
                'crew_build_id' => $crewBuild->id,
                'master_name' => $crewMaster ? $crewMaster->display_name : $player->master_name,
                'master_id' => $crewMaster ? $crewMaster->id : $player->master_id,
                'active_crew_upgrade_id' => $activeUpgradeId,
            ]);

            // Copy crew into game_crew_members
            $this->copyCrewToGame($game, $player, $crewBuild);

            // Calculate soulstone pool from inserted crew
            $totalSpent = GameCrewMember::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->sum('cost');
            $remaining = $game->encounter_size - $totalSpent;
            $pool = $remaining > 6 ? 6 : max(0, $remaining);
            $player->update(['soulstone_pool' => $pool]);
        });

        // In solo, opponent crew is optional — check with skip support.
        // Pessimistic lock to prevent double-advance.
        $bothDone = DB::transaction(function () use ($game) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);
            $done = $locked->players()
                ->where(fn ($q) => $q->whereNotNull('crew_build_id')->orWhere('crew_skipped', true))
                ->count() === 2;
            if ($done && $locked->status === GameStatusEnum::CrewSelect) {
                $locked->update(['status' => GameStatusEnum::SchemeSelect]);
            }

            return $done;
        });

        if (! $game->is_solo) {
            if ($bothDone) {
                broadcast(new GameStatusChanged($game))->toOthers();
            }
            broadcast(new GameSetupStepCompleted($game, $player, 'crew'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function skipCrew(Game $game): JsonResponse
    {
        if (! $game->is_solo) {
            return response()->json(['error' => 'Only available in solo mode'], 403);
        }
        if ($game->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Not the solo game owner'], 403);
        }

        $player = $this->getPlayer($game, 2);

        if ($game->status !== GameStatusEnum::CrewSelect) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $player->update(['crew_skipped' => true]);

        $bothDone = DB::transaction(function () use ($game) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);
            $done = $locked->players()
                ->where(fn ($q) => $q->whereNotNull('crew_build_id')->orWhere('crew_skipped', true))
                ->count() === 2;
            if ($done && $locked->status === GameStatusEnum::CrewSelect) {
                $locked->update(['status' => GameStatusEnum::SchemeSelect]);
            }

            return $done;
        });

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function submitScheme(SubmitSchemeRequest $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        // Allow scheme selection during in_progress for solo opponent (hidden scheme)
        $allowedStatuses = [GameStatusEnum::SchemeSelect];
        if ($game->is_solo) {
            $allowedStatuses[] = GameStatusEnum::InProgress;
        }

        if (! in_array($game->status, $allowedStatuses)) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validated();

        // Verify scheme is in the pool or in the next schemes chain
        $validSchemeIds = $game->scheme_pool ?? [];
        if (! in_array($validated['scheme_id'], $validSchemeIds)) {
            // Also allow next schemes from current scheme chain
            if ($player->current_scheme_id) {
                $currentScheme = Scheme::find($player->current_scheme_id);
                if ($currentScheme) {
                    $validSchemeIds = array_merge($validSchemeIds, array_filter([
                        $currentScheme->next_scheme_one_id,
                        $currentScheme->next_scheme_two_id,
                        $currentScheme->next_scheme_three_id,
                    ]));
                }
            }
            if (! in_array($validated['scheme_id'], $validSchemeIds)) {
                return response()->json(['error' => 'Scheme not in pool'], 422);
            }
        }

        // Set scheme and derive the initial pool (follow-ups of the chosen scheme)
        $chosenScheme = Scheme::find($validated['scheme_id']);
        $newPool = $chosenScheme ? array_values(array_filter([
            $chosenScheme->next_scheme_one_id,
            $chosenScheme->next_scheme_two_id,
            $chosenScheme->next_scheme_three_id,
        ])) : [];

        $playerUpdate = [
            'current_scheme_id' => $validated['scheme_id'],
            'scheme_pool' => ! empty($newPool) ? $newPool : ($game->scheme_pool ?? []),
        ];
        // Persist scheme requirements only when at least one field has content
        // — a blank object would wipe previously-saved notes on re-submit.
        if (! empty($validated['scheme_notes']) && array_filter($validated['scheme_notes'])) {
            $playerUpdate['scheme_notes'] = $validated['scheme_notes'];
        }
        $player->update($playerUpdate);

        // Only advance status during scheme_select phase
        if ($game->status === GameStatusEnum::SchemeSelect) {
            // Solo: advance immediately when the user picks their own scheme — opponent scheme is set during gameplay
            if ($game->is_solo) {
                $game->update([
                    'status' => GameStatusEnum::InProgress,
                    'current_turn' => 1,
                ]);
                // Initialize opponent's scheme pool to the game pool. Using model
                // update() (vs query builder) so the 'array' cast handles encoding.
                $opponent = $game->players()->where('slot', 2)->first();
                $opponent?->update(['scheme_pool' => $game->scheme_pool ?? []]);
            } else {
                $bothDone = DB::transaction(function () use ($game) {
                    /** @var Game $locked */
                    $locked = Game::lockForUpdate()->find($game->id);
                    $done = $locked->players()->whereNotNull('current_scheme_id')->count() === 2;
                    if ($done && $locked->status === GameStatusEnum::SchemeSelect) {
                        $locked->update([
                            'status' => GameStatusEnum::InProgress,
                            'current_turn' => 1,
                        ]);
                    }

                    return $done;
                });
                if ($bothDone) {
                    broadcast(new GameStatusChanged($game->fresh()))->toOthers();
                }
                broadcast(new GameSetupStepCompleted($game, $player, 'scheme'))->toOthers();
            }
        }

        return response()->json(['success' => true]);
    }

    public function updateOpponentName(UpdateOpponentNameRequest $request, Game $game): RedirectResponse
    {
        $validated = $request->validated();

        $game->players()->where('slot', 2)->update(['opponent_name' => $validated['opponent_name']]);

        return back();
    }

    public function swapRoles(Game $game): RedirectResponse
    {
        if (! $game->is_solo || $game->creator_id !== Auth::id()) {
            abort(403, 'Only available in solo mode');
        }

        /** @var GamePlayer|null $p1 */
        $p1 = $game->players()->where('slot', 1)->first();
        /** @var GamePlayer|null $p2 */
        $p2 = $game->players()->where('slot', 2)->first();

        if ($p1 && $p2) {
            $r1 = $p1->role;
            $p1->update(['role' => $p2->role]);
            $p2->update(['role' => $r1]);
        }

        return back();
    }

    private function getPlayer(Game $game, ?int $slot = null): GamePlayer
    {
        // $request->integer() returns 0 when key is absent — treat as null
        if ($slot === 0) {
            $slot = null;
        }

        // Validate slot parameter
        if ($slot !== null && ! in_array($slot, [1, 2], true)) {
            abort(422, 'Slot must be 1 or 2');
        }

        // In solo mode with a slot specified, return that slot's player
        if ($game->is_solo && $slot) {
            // Verify the caller is the game creator
            if ($game->creator_id !== Auth::id()) {
                abort(403, 'Not the solo game owner');
            }
            /** @var GamePlayer|null $player */
            $player = $game->players()->where('slot', $slot)->first();
        } else {
            /** @var GamePlayer|null $player */
            $player = $game->players()->where('user_id', Auth::id())->first();
        }

        if (! $player) {
            abort(403, 'Not a participant in this game');
        }

        return $player;
    }

    private function copyCrewToGame(Game $game, GamePlayer $player, CrewBuild $crewBuild): void
    {
        // Delete existing crew members for this player (in case of re-selection)
        GameCrewMember::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->delete();

        $master = Character::with('miniatures', 'keywords')
            ->find($crewBuild->master_id);

        if (! $master) {
            return;
        }

        $miniatureSelections = $crewBuild->miniature_selections ?? [];
        $miniatureIndexes = []; // Tracks per-character consumption index for multi-copy models
        $leaderKeywordSlugs = $master->keywords->pluck('slug')->toArray();
        $sortOrder = 0;

        // Add leader
        $this->createCrewMember($game, $player, $master, 'leader', 0, $sortOrder++, $miniatureSelections, $miniatureIndexes);

        // Add totem
        if ($master->has_totem_id) {
            $totem = Character::with('miniatures')->find($master->has_totem_id);
            if ($totem) {
                $totemCount = max(1, $totem->count ?? 1);
                for ($i = 0; $i < $totemCount; $i++) {
                    $this->createCrewMember($game, $player, $totem, 'totem', 0, $sortOrder++, $miniatureSelections, $miniatureIndexes);
                }
            }
        }

        // Add crew members
        $crewCharacterIds = $crewBuild->crew_data ?? [];
        $crewCharacters = Character::with('miniatures', 'keywords', 'characteristics')
            ->whereIn('id', $crewCharacterIds)
            ->get()
            ->keyBy('id');

        foreach ($crewCharacterIds as $charId) {
            $character = $crewCharacters->get($charId);
            if (! $character) {
                continue;
            }

            $sharesKeyword = $character->keywords->pluck('slug')->intersect($leaderKeywordSlugs)->isNotEmpty();
            $isVersatile = $character->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');
            $category = $sharesKeyword ? 'in-keyword' : ($isVersatile ? 'versatile' : 'ook');
            $effectiveCost = $category === 'ook' ? ($character->cost + 1) : $character->cost;

            $this->createCrewMember($game, $player, $character, $category, $effectiveCost, $sortOrder++, $miniatureSelections, $miniatureIndexes);
        }

        // Add custom crew members
        foreach ($crewBuild->custom_crew_data ?? [] as $customEntry) {
            $customKeywords = collect($customEntry['keywords'] ?? [])
                ->pluck('name')
                ->map(fn ($n) => Str::slug($n))
                ->toArray();

            $sharesKeyword = ! empty(array_intersect($customKeywords, $leaderKeywordSlugs));
            $isVersatile = in_array('versatile', array_map('strtolower', $customEntry['characteristics'] ?? []));
            $category = $sharesKeyword ? 'in-keyword' : ($isVersatile ? 'versatile' : 'ook');
            $baseCost = $customEntry['cost'] ?? 0;
            $effectiveCost = $category === 'ook' ? ($baseCost + 1) : $baseCost;

            GameCrewMember::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'character_id' => null,
                'custom_character_id' => $customEntry['custom_character_id'] ?? null,
                'display_name' => $customEntry['display_name'] ?? 'Custom Character',
                'faction' => $customEntry['faction'] ?? $player->getRawOriginal('faction'),
                'current_health' => $customEntry['health'] ?? 1,
                'max_health' => $customEntry['health'] ?? 1,
                'defense' => $customEntry['defense'] ?? null,
                'willpower' => $customEntry['willpower'] ?? null,
                'speed' => $customEntry['speed'] ?? null,
                'size' => $customEntry['size'] ?? null,
                'cost' => $effectiveCost,
                'station' => $customEntry['station'] ?? null,
                'hiring_category' => $category,
                'front_image' => $customEntry['front_image'] ?? null,
                'back_image' => $customEntry['back_image'] ?? null,
                'is_custom' => true,
                'attached_upgrades' => [],
                'attached_tokens' => [],
                'attached_markers' => [],
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function createCrewMember(Game $game, GamePlayer $player, Character $character, string $category, int $cost, int $sortOrder, array $miniatureSelections = [], array &$miniatureIndexes = []): void
    {
        // Use the miniature selected in the Crew Builder, or fall back to first.
        // miniature_selections can be { "charId": miniatureId } (single) or { "charId": [id1, id2, ...] } (multi).
        // For multi, consume in order using the index counter.
        $charKey = (string) $character->id;
        $selection = $miniatureSelections[$charKey] ?? null;
        $selectedMiniId = null;

        if (is_array($selection)) {
            $idx = $miniatureIndexes[$charKey] ?? 0;
            $selectedMiniId = $selection[$idx] ?? null;
            $miniatureIndexes[$charKey] = $idx + 1;
        } elseif ($selection) {
            $selectedMiniId = $selection;
        }

        $miniature = $selectedMiniId
            ? $character->miniatures->firstWhere('id', $selectedMiniId) ?? $character->miniatures->first()
            : $character->miniatures->first();

        GameCrewMember::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'character_id' => $character->id,
            'display_name' => $miniature ? $miniature->display_name : $character->display_name,
            'faction' => $character->getRawOriginal('faction'),
            'current_health' => $character->health,
            'max_health' => $character->health,
            'defense' => $character->defense,
            'willpower' => $character->willpower,
            'speed' => $character->speed,
            'size' => $character->size,
            'cost' => $cost,
            'station' => $character->station?->value,
            'hiring_category' => $category,
            'front_image' => $miniature?->front_image,
            'back_image' => $miniature?->back_image,
            'attached_upgrades' => [],
            'attached_tokens' => [],
            'attached_markers' => [],
            'sort_order' => $sortOrder,
        ]);
    }
}
