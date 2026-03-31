<?php

namespace App\Http\Controllers;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameStatusEnum;
use App\Events\GameSetupStepCompleted;
use App\Events\GameStatusChanged;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Scheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GameSetupController extends Controller
{
    public function submitFaction(Request $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        if ($game->status !== GameStatusEnum::FactionSelect) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validate([
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ]);

        $player->update(['faction' => $validated['faction']]);

        $bothDone = $game->players()->whereNotNull('faction')->count() === 2;
        if ($bothDone) {
            $game->update(['status' => GameStatusEnum::MasterSelect]);
        }

        if (! $game->is_solo) {
            if ($bothDone) {
                broadcast(new GameStatusChanged($game))->toOthers();
            }
            broadcast(new GameSetupStepCompleted($game, $player, 'faction'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function submitMaster(Request $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        // Allow changing title during crew_select (before crew is locked in)
        if (! in_array($game->status, [GameStatusEnum::MasterSelect, GameStatusEnum::CrewSelect])) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validate([
            'master_name' => ['required', 'string', 'max:255'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ]);

        // Find master character by display_name or name + faction
        $master = Character::where('station', CharacterStationEnum::Master->value)
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

        $bothDone = $game->players()->whereNotNull('master_name')->count() === 2;
        $statusChanged = false;
        if ($bothDone && $game->status === GameStatusEnum::MasterSelect) {
            $game->update(['status' => GameStatusEnum::CrewSelect]);
            $statusChanged = true;
        }

        if (! $game->is_solo) {
            if ($statusChanged) {
                broadcast(new GameStatusChanged($game))->toOthers();
            }
            broadcast(new GameSetupStepCompleted($game, $player, 'master'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function submitCrew(Request $request, Game $game): JsonResponse
    {
        $player = $this->getPlayer($game, $request->integer('slot'));

        if ($game->status !== GameStatusEnum::CrewSelect) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $validated = $request->validate([
            'crew_build_id' => ['required', 'exists:crew_builds,id'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ]);

        $crewBuild = CrewBuild::findOrFail($validated['crew_build_id']);

        // Verify ownership (solo mode can use any of the user's crews for either slot)
        if ($crewBuild->user_id !== Auth::id()) {
            return response()->json(['error' => 'Not your crew'], 403);
        }

        DB::transaction(function () use ($player, $game, $crewBuild) {
            // Update crew selection and lock in the master title from the crew build
            $crewMaster = $crewBuild->master;
            $player->update([
                'crew_build_id' => $crewBuild->id,
                'master_name' => $crewMaster ? $crewMaster->display_name : $player->master_name,
                'master_id' => $crewMaster ? $crewMaster->id : $player->master_id,
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

        // In solo, opponent crew is optional — check with skip support
        $crewDoneCount = $game->players()
            ->where(fn ($q) => $q->whereNotNull('crew_build_id')->orWhere('crew_skipped', true))
            ->count();
        $bothDone = $crewDoneCount === 2;
        if ($bothDone) {
            $game->update(['status' => GameStatusEnum::SchemeSelect]);
        }

        if (! $game->is_solo) {
            if ($bothDone) {
                broadcast(new GameStatusChanged($game))->toOthers();
            }
            broadcast(new GameSetupStepCompleted($game, $player, 'crew'))->toOthers();
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function skipCrew(Request $request, Game $game): JsonResponse
    {
        if (! $game->is_solo) {
            return response()->json(['error' => 'Only available in solo mode'], 403);
        }

        $player = $this->getPlayer($game, 2);

        if ($game->status !== GameStatusEnum::CrewSelect) {
            return response()->json(['error' => 'Invalid game state'], 422);
        }

        $player->update(['crew_skipped' => true]);

        $crewDoneCount = $game->players()
            ->where(fn ($q) => $q->whereNotNull('crew_build_id')->orWhere('crew_skipped', true))
            ->count();
        $bothDone = $crewDoneCount === 2;
        if ($bothDone) {
            $game->update(['status' => GameStatusEnum::SchemeSelect]);
        }

        return response()->json(['success' => true, 'both_done' => $bothDone]);
    }

    public function submitScheme(Request $request, Game $game): JsonResponse
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

        $validated = $request->validate([
            'scheme_id' => ['required', 'integer'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ]);

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

        $player->update(['current_scheme_id' => $validated['scheme_id']]);

        // Only advance status during scheme_select phase
        if ($game->status === GameStatusEnum::SchemeSelect) {
            // Solo: advance immediately when the user picks their own scheme — opponent scheme is set during gameplay
            if ($game->is_solo) {
                $game->update([
                    'status' => GameStatusEnum::InProgress,
                    'current_turn' => 1,
                ]);
            } else {
                $bothDone = $game->players()->whereNotNull('current_scheme_id')->count() === 2;
                if ($bothDone) {
                    $game->update([
                        'status' => GameStatusEnum::InProgress,
                        'current_turn' => 1,
                    ]);
                    broadcast(new GameStatusChanged($game))->toOthers();
                }
                broadcast(new GameSetupStepCompleted($game, $player, 'scheme'))->toOthers();
            }
        }

        return response()->json(['success' => true]);
    }

    public function updateOpponentName(Request $request, Game $game): JsonResponse
    {
        if (! $game->is_solo || $game->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Only available in solo mode'], 403);
        }

        $validated = $request->validate([
            'opponent_name' => ['required', 'string', 'max:255'],
        ]);

        $game->players()->where('slot', 2)->update(['opponent_name' => $validated['opponent_name']]);

        return response()->json(['success' => true]);
    }

    public function swapRoles(Game $game): JsonResponse
    {
        if (! $game->is_solo || $game->creator_id !== Auth::id()) {
            return response()->json(['error' => 'Only available in solo mode'], 403);
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

        return response()->json(['success' => true]);
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
        $leaderKeywordSlugs = $master->keywords->pluck('slug')->toArray();
        $sortOrder = 0;

        // Add leader
        $this->createCrewMember($game, $player, $master, 'leader', 0, $sortOrder++, $miniatureSelections);

        // Add totem
        if ($master->has_totem_id) {
            $totem = Character::with('miniatures')->find($master->has_totem_id);
            if ($totem) {
                $totemCount = max(1, $totem->count ?? 1);
                for ($i = 0; $i < $totemCount; $i++) {
                    $this->createCrewMember($game, $player, $totem, 'totem', 0, $sortOrder++, $miniatureSelections);
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

            $this->createCrewMember($game, $player, $character, $category, $effectiveCost, $sortOrder++, $miniatureSelections);
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

    private function createCrewMember(Game $game, GamePlayer $player, Character $character, string $category, int $cost, int $sortOrder, array $miniatureSelections = []): void
    {
        // Use the miniature selected in the Crew Builder, or fall back to first
        $selectedMiniId = $miniatureSelections[(string) $character->id] ?? null;
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
