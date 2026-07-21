<?php

namespace App\Http\Controllers\Game;

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\TokenRemovalTimingEnum;
use App\Enums\TournamentGameResultEnum;
use App\Events\GameCrewMemberUpdated;
use App\Events\GameStatusChanged;
use App\Events\GameTurnAdvanced;
use App\Events\TournamentUpdated;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Game\Concerns\BroadcastsGameEvents;
use App\Http\Requests\Games\ReplaceCrewMemberRequest;
use App\Http\Requests\Games\SubmitTurnRequest;
use App\Http\Requests\Games\SummonCrewMemberRequest;
use App\Http\Requests\Games\SwapCrewUpgradeRequest;
use App\Http\Requests\Games\UpdateCrewMemberRequest;
use App\Http\Requests\Games\UpdateCrewUpgradePowerBarRequest;
use App\Http\Requests\Games\UpdateSchemeNotesRequest;
use App\Http\Requests\Games\UpdateSoulstonePoolRequest;
use App\Models\Campaign\CampaignGame;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\LootCard;
use App\Models\Scheme;
use App\Models\Token;
use App\Models\TournamentGame;
use App\Services\LootDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GamePlayController extends Controller
{
    use BroadcastsGameEvents;

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

        // Detect loot upgrades being removed and push their card_id back to
        // the discard pile so they're not lost from the deck state.
        if (
            $game->format === \App\Enums\GameFormatEnum::BonanzaBrawl
            && array_key_exists('attached_upgrades', $validated)
        ) {
            $prevLoot = collect($gameCrewMember->attached_upgrades ?? [])
                ->filter(fn ($u) => is_array($u) && ! empty($u['loot_card_id']))
                ->pluck('loot_card_id');
            $nextLootIds = collect($validated['attached_upgrades'] ?? [])
                ->filter(fn ($u) => is_array($u) && ! empty($u['loot_card_id']))
                ->pluck('loot_card_id')
                ->flip();
            $service = app(LootDeckService::class);
            foreach ($prevLoot as $cardId) {
                if (! $nextLootIds->has($cardId)) {
                    $service->discardCard($game, (int) $cardId);
                }
            }
        }

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
                    // "Plentiful" is an Equipment-card mechanic — a generic Injury
                    // (e.g. "Lingering Wound") has no such limit and can legitimately
                    // apply to any number of different models across a crew, so it
                    // must never be capped by the same "no plentiful set → 1" default
                    // that's correct for Equipment.
                    if ($upgrade->campaign_upgrade_kind === 'injury') {
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

        // "Until end of activation" tokens fall off the moment a model is
        // activated for the turn. Strip them on the false → true transition and
        // report them so the client can reflect the removal without a reload.
        $removedTokens = [];
        if (! empty($validated['is_activated']) && ! $gameCrewMember->is_activated) {
            $endOfActivationIds = Token::where('removal_timing', TokenRemovalTimingEnum::EndOfActivation)->pluck('id');
            if ($endOfActivationIds->isNotEmpty()) {
                $source = array_key_exists('attached_tokens', $validated)
                    ? $validated['attached_tokens']
                    : ($gameCrewMember->attached_tokens ?? []);
                [$keep, $drop] = collect($source)->partition(fn ($t) => ! $endOfActivationIds->contains($t['id'] ?? null));
                if ($drop->isNotEmpty()) {
                    $validated['attached_tokens'] = $keep->values()->all();
                    $removedTokens = $drop->map(fn ($t) => ['id' => (int) ($t['id'] ?? 0), 'name' => $t['name'] ?? ''])->values()->all();
                }
            }
        }

        $gameCrewMember->update($validated);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'updated'));

        return response()->json(['success' => true, 'removed_tokens' => $removedTokens]);
    }

    /**
     * Quick Add: attach one token to many of the player's live crew members at
     * once. Members are validated to belong to the player (policy) and alive.
     */
    public function bulkAttachToken(Request $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);

        $validated = $request->validate([
            'token_id' => ['required', 'integer', 'exists:tokens,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],
        ]);

        $token = Token::findOrFail($validated['token_id']);

        $members = GameCrewMember::where('game_id', $game->id)
            ->whereIn('id', $validated['member_ids'])
            ->where('is_killed', false)
            ->get();

        // Authorize every member up front so we never half-apply then 403.
        foreach ($members as $member) {
            $this->authorize('updateCrewMember', [$game, $member]);
        }

        foreach ($members as $member) {
            $tokens = collect($member->attached_tokens ?? []);
            if (! $tokens->contains('id', $token->id)) {
                $tokens->push(['id' => $token->id, 'name' => $token->name]);
                $member->update(['attached_tokens' => $tokens->values()->all()]);
            }
        }

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'updated'));

        return response()->json(['success' => true, 'attached' => $members->count()]);
    }

    /**
     * Strip every `end_of_turn` token off all of the game's models. Returns the
     * removed {member, token} pairs so the client can offer an Undo. Called from
     * the turn-advance paths; never throws so it can't block the turn.
     *
     * @return array<int, array{member_id: int, member_name: string, token_id: int, token_name: string}>
     */
    private function removeEndOfTurnTokens(Game $game): array
    {
        $endOfTurnIds = Token::where('removal_timing', TokenRemovalTimingEnum::EndOfTurn)->pluck('id');
        if ($endOfTurnIds->isEmpty()) {
            return [];
        }

        $removed = [];
        foreach (GameCrewMember::where('game_id', $game->id)->get() as $member) {
            [$keep, $drop] = collect($member->attached_tokens ?? [])
                ->partition(fn ($t) => ! $endOfTurnIds->contains($t['id'] ?? null));

            if ($drop->isNotEmpty()) {
                $member->update(['attached_tokens' => $keep->values()->all()]);
                foreach ($drop as $t) {
                    $removed[] = [
                        'member_id' => $member->id,
                        'member_name' => $member->display_name,
                        'token_id' => (int) $t['id'],
                        'token_name' => $t['name'] ?? '',
                    ];
                }
            }
        }

        return $removed;
    }

    /**
     * Undo an end-of-turn removal: re-attach the given {member, token} pairs.
     */
    public function restoreTokens(Request $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);

        $validated = $request->validate([
            'tokens' => ['required', 'array', 'min:1'],
            'tokens.*.member_id' => ['required', 'integer'],
            'tokens.*.token_id' => ['required', 'integer'],
            'tokens.*.token_name' => ['nullable', 'string'],
        ]);

        $byMember = collect($validated['tokens'])->groupBy('member_id');
        $members = GameCrewMember::where('game_id', $game->id)
            ->whereIn('id', $byMember->keys())
            ->get();

        foreach ($members as $member) {
            $this->authorize('updateCrewMember', [$game, $member]);

            $tokens = collect($member->attached_tokens ?? []);
            foreach ($byMember[$member->id] as $t) {
                if (! $tokens->contains('id', (int) $t['token_id'])) {
                    $tokens->push(['id' => (int) $t['token_id'], 'name' => $t['token_name'] ?? '']);
                }
            }
            $member->update(['attached_tokens' => $tokens->values()->all()]);
        }

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'updated'));

        return response()->json(['success' => true]);
    }

    public function killCrewMember(Game $game, GameCrewMember $gameCrewMember): JsonResponse
    {
        $this->assertInProgress($game);
        $this->authorize('updateCrewMember', [$game, $gameCrewMember]);

        // Drop loot markers BEFORE flipping is_killed — the service reads the
        // member's current attached_upgrades to find loot entries, and we want
        // them stripped on the same row update so the UI doesn't show ghost
        // loot on a dead model. Bonanza-only; standard games never have loot.
        $droppedMarkers = [];
        if ($game->format === \App\Enums\GameFormatEnum::BonanzaBrawl && ! $gameCrewMember->is_killed) {
            $droppedMarkers = app(LootDeckService::class)->dropMarkersOnDeath($game, $gameCrewMember);
            $gameCrewMember->refresh();
        }

        // Idempotent: if already killed, skip DB write + broadcast but
        // still return replacements so the UI can handle the state.
        if (! $gameCrewMember->is_killed) {
            $gameCrewMember->update(['is_killed' => true, 'current_health' => 0]);
        }

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'killed'));

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
            'dropped_markers' => $droppedMarkers,
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

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'revived'));

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

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'summoned'));

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

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'replaced'));

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

        $master = $player->master;
        if (! $master) {
            return response()->json(['error' => 'No model selected'], 422);
        }

        // Standard format: only Swappable masters can swap, and the upgrade
        // must belong to the master's direct crew upgrade list.
        // Bonanza: any keyword-derived crew upgrade is valid (the picked model
        // usually isn't even a master, so crew_upgrade_mode is irrelevant).
        if ($game->format === \App\Enums\GameFormatEnum::BonanzaBrawl) {
            $validIds = $master->loadMissing('keywords.crewUpgrades')->keywords
                ->flatMap(fn ($k) => $k->crewUpgrades)
                ->pluck('id')
                ->unique()
                ->toArray();
        } else {
            if ($master->crew_upgrade_mode !== \App\Enums\CrewUpgradeModeEnum::Swappable) {
                return response()->json(['error' => 'Crew upgrades are not swappable for this master'], 422);
            }
            $validIds = $master->crewUpgrades->pluck('id')->toArray();
        }

        if (! in_array($validated['active_crew_upgrade_id'], $validIds)) {
            return response()->json(['error' => 'Upgrade not available for this model'], 422);
        }

        $player->update(['active_crew_upgrade_id' => $validated['active_crew_upgrade_id']]);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'updated'));

        return response()->json(['success' => true]);
    }

    /**
     * Set the live counter for one of the master's crew-level upgrades that has
     * a power_bar_count (e.g. token-pool style upgrades). Stored as a sparse
     * map keyed by upgrade_id on the player; max comes from upgrades.power_bar_count
     * — no need to duplicate it here.
     */
    public function updateCrewUpgradePowerBar(UpdateCrewUpgradePowerBarRequest $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        $validated = $request->validated();

        $master = $player->master;
        if (! $master) {
            return response()->json(['error' => 'No master selected'], 422);
        }

        // Authorize: upgrade must belong to this master's crew upgrades
        // AND have a non-null power_bar_count (otherwise it has no bar to track).
        $upgrade = $master->crewUpgrades->firstWhere('id', $validated['upgrade_id']);
        if (! $upgrade || $upgrade->power_bar_count === null) {
            return response()->json(['error' => 'Upgrade does not have a power bar'], 422);
        }

        // Cap to the upgrade's max so we never persist a value > power_bar_count.
        $clamped = min((int) $validated['current_power_bar'], (int) $upgrade->power_bar_count);

        $bars = $player->crew_upgrade_power_bars ?? [];
        $bars[(string) $upgrade->id] = $clamped;
        $player->update(['crew_upgrade_power_bars' => $bars]);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'updated'));

        return response()->json(['success' => true, 'current_power_bar' => $clamped]);
    }

    public function updateSoulstonePool(UpdateSoulstonePoolRequest $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);
        $slot = $request->integer('slot');
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);
        $validated = $request->validated();
        $player->update(['soulstone_pool' => $validated['soulstone_pool']]);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'soulstone_pool'));

        return response()->json(['success' => true]);
    }

    /**
     * Manual VP delta for Bonanza Brawl games. The format scores by event
     * (kill = +3, damage at full HP = +1, friendly Loot Marker on Treasure
     * Pile = +1, etc.) — there's no per-turn strategy/scheme breakdown to
     * shoehorn into GameTurn rows, so we just bump total_points directly.
     * Negative deltas are allowed (death = -3 to a minimum of 0).
     */
    public function adjustBonanzaVp(Request $request, Game $game): JsonResponse
    {
        $this->assertInProgress($game);

        if ($game->format !== \App\Enums\GameFormatEnum::BonanzaBrawl) {
            return response()->json(['error' => 'This action is only available in Bonanza Brawl games.'], 422);
        }

        $validated = $request->validate([
            'slot' => ['sometimes', 'integer', 'in:1,2'],
            'delta' => ['required', 'integer', 'min:-20', 'max:20'],
        ]);

        $slot = (int) ($validated['slot'] ?? 0);
        $player = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);

        // Floor at 0 — Bonanza explicitly says "to a minimum of 0 Total VP".
        $next = max(0, $player->total_points + (int) $validated['delta']);
        $player->update(['total_points' => $next]);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'bonanza_vp'));

        return response()->json(['success' => true, 'total_points' => $next]);
    }

    public function drawLoot(Request $request, Game $game): JsonResponse
    {
        $this->assertBonanzaInProgress($game);

        $card = app(LootDeckService::class)->draw($game);
        if (! $card) {
            return response()->json(['error' => 'The Loot deck is empty.'], 422);
        }

        $card->load([
            // Full detail (not just names) so the side-picker can render the same
            // ActionCard/AbilityCard/LootTriggerDisplay the reference page does —
            // column lists mirror BonanzaLootDeckController's public reference page.
            'sideAActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
            'sideBActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
            'sideAActions.triggers:id,name,slug,suits,stone_cost,description',
            'sideBActions.triggers:id,name,slug,suits,stone_cost,description',
            'sideAAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
            'sideBAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
            'sideATriggers:id,name,slug,suits,stone_cost,description',
            'sideBTriggers:id,name,slug,suits,stone_cost,description',
        ]);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'loot_drawn'));

        return response()->json([
            'success' => true,
            'card' => $card,
            'deck_size' => count($game->fresh()->loot_state['deck'] ?? []),
            'discard_size' => count($game->fresh()->loot_state['discard'] ?? []),
        ]);
    }

    public function selectLoot(Request $request, Game $game): JsonResponse
    {
        $this->assertBonanzaInProgress($game);

        $validated = $request->validate([
            'game_crew_member_id' => ['required', 'integer', 'exists:game_crew_members,id'],
            'loot_card_id' => ['required', 'integer', 'exists:loot_cards,id'],
            'side' => ['required', 'string', 'in:a,b'],
        ]);

        /** @var GameCrewMember $member */
        $member = GameCrewMember::findOrFail($validated['game_crew_member_id']);
        $this->authorize('updateCrewMember', [$game, $member]);

        $service = app(LootDeckService::class);
        $card = $service->selectCard($game, $validated['loot_card_id']);
        if (! $card) {
            return response()->json([
                'error' => 'That loot card is already in play (attached to a model or dropped as a marker).',
            ], 422);
        }

        $service->attachToMember($member, $card, $validated['side']);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'loot_attached'));

        return response()->json([
            'success' => true,
            'deck_size' => count($game->fresh()->loot_state['deck'] ?? []),
            'discard_size' => count($game->fresh()->loot_state['discard'] ?? []),
        ]);
    }

    public function attachLoot(Request $request, Game $game): JsonResponse
    {
        $this->assertBonanzaInProgress($game);

        $validated = $request->validate([
            'game_crew_member_id' => ['required', 'integer', 'exists:game_crew_members,id'],
            'loot_card_id' => ['required', 'integer', 'exists:loot_cards,id'],
            'side' => ['required', 'string', 'in:a,b'],
        ]);

        /** @var GameCrewMember $member */
        $member = GameCrewMember::findOrFail($validated['game_crew_member_id']);
        $this->authorize('updateCrewMember', [$game, $member]);

        $card = LootCard::findOrFail($validated['loot_card_id']);
        app(LootDeckService::class)->attachToMember($member, $card, $validated['side']);

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'loot_attached'));

        return response()->json(['success' => true]);
    }

    public function yoinkLoot(Request $request, Game $game): JsonResponse
    {
        $this->assertBonanzaInProgress($game);

        $validated = $request->validate([
            'game_crew_member_id' => ['required', 'integer', 'exists:game_crew_members,id'],
            'marker_id' => ['required', 'string'],
            'side' => ['required', 'string', 'in:a,b'],
        ]);

        /** @var GameCrewMember $member */
        $member = GameCrewMember::findOrFail($validated['game_crew_member_id']);
        $this->authorize('updateCrewMember', [$game, $member]);

        $card = app(LootDeckService::class)->yoinkMarker($game, $member, $validated['marker_id'], $validated['side']);
        if (! $card) {
            return response()->json(['error' => 'Loot marker not found.'], 404);
        }

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'loot_yoinked'));

        return response()->json(['success' => true, 'card_id' => $card->id]);
    }

    private function assertBonanzaInProgress(Game $game): void
    {
        $this->assertInProgress($game);
        if ($game->format !== \App\Enums\GameFormatEnum::BonanzaBrawl) {
            abort(422, 'Loot deck actions are only available in Bonanza Brawl games.');
        }
    }

    /**
     * Bonanza turn advance. Standard format uses submitTurnScore (which carries
     * strategy + scheme points and gates on both players submitting), but
     * Bonanza is event-driven VP only — there's nothing to score per turn, so
     * the user just bumps the counter when they're ready. Hitting the last
     * turn auto-finalizes the game with no declared winner (is_tie=true) since
     * slot 2 is inert in the personal-tracker mode.
     */
    public function advanceBonanzaTurn(Request $request, Game $game): JsonResponse
    {
        $this->assertBonanzaInProgress($game);

        $result = DB::transaction(function () use ($game) {
            /** @var Game $locked */
            $locked = Game::lockForUpdate()->find($game->id);

            if ($locked->status !== GameStatusEnum::InProgress) {
                return ['game_complete' => true];
            }

            // Reset is_activated on the user's models for the new turn — same
            // hygiene step the standard submitTurnScore does so the activation
            // toggle stays meaningful.
            GameCrewMember::where('game_id', $locked->id)->update(['is_activated' => false]);

            if ($locked->current_turn >= $locked->max_turns) {
                // End of turn N where N == max_turns → game over.
                $locked->players()->update(['is_game_complete' => true]);
                $this->finalizeBonanzaGame($locked);

                return ['game_complete' => true];
            }

            $locked->increment('current_turn');
            $removed = $this->removeEndOfTurnTokens($locked);

            return ['game_complete' => false, 'current_turn' => $locked->current_turn, 'removed_tokens' => $removed];
        });

        if (! $game->is_solo || $game->is_observable) {
            if ($result['game_complete']) {
                broadcast(new GameStatusChanged($game->fresh()));
            } else {
                broadcast(new GameTurnAdvanced($game->fresh()))->toOthers();
            }
        }

        return response()->json(['success' => true, ...$result]);
    }

    /**
     * Bonanza-flavored finalize: no winner determination (slot 2 is inert in
     * personal-tracker mode, so comparing total_points wouldn't be meaningful).
     * Mark Completed with is_tie=true so the summary view renders "no winner"
     * cleanly instead of crowning the user by default.
     */
    private function finalizeBonanzaGame(Game $game): void
    {
        // Snapshot the user's crew for the final turn, mirroring submitTurnScore.
        $players = $game->players()->get();
        foreach ($players as $player) {
            /** @var GamePlayer $player */
            $existing = GameTurn::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('turn_number', $game->current_turn)
                ->first();
            if (! $existing) {
                GameTurn::create([
                    'game_id' => $game->id,
                    'turn_number' => $game->current_turn,
                    'game_player_id' => $player->id,
                    'strategy_points' => 0,
                    'scheme_points' => 0,
                    'points_scored' => 0,
                    'crew_snapshot' => $this->buildCrewSnapshot($game->id, $player->id),
                ]);
            }
        }

        $game->update([
            'status' => GameStatusEnum::Completed,
            'completed_at' => now(),
            'is_tie' => true,
            'winner_id' => null,
            'winner_slot' => null,
        ]);
    }

    /**
     * Edit a previously-submitted turn's points. Scoped to the immediately
     * preceding turn (current_turn - 1) so users can correct a mis-clicked
     * score without giving them a full history-editor (which would muddy
     * tournament integrations).
     */
    public function editTurnScore(Request $request, Game $game, GameTurn $turn): JsonResponse
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return response()->json(['error' => 'Game not in progress'], 422);
        }

        if ($turn->game_id !== $game->id) {
            abort(404);
        }

        if ($turn->turn_number !== $game->current_turn - 1) {
            return response()->json(['error' => 'Only the most recently submitted turn can be edited'], 422);
        }

        $player = GamePlayer::findOrFail($turn->game_player_id);
        $slot = $request->integer('slot') ?: null;
        $actor = ($game->is_solo && $slot) ? $this->getPlayerForSlot($game, $slot) : $this->getMyPlayer($game);
        if (! $game->is_solo && $actor->id !== $player->id) {
            abort(403);
        }

        $validated = $request->validate([
            'strategy_points' => ['required', 'integer', 'min:0', 'max:2'],
            'scheme_points' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        DB::transaction(function () use ($game, $player, $turn, $validated) {
            $bonusUsedThisTurn = $validated['strategy_points'] > 1;

            $turn->update([
                'strategy_points' => $validated['strategy_points'],
                'strategy_bonus_used' => $bonusUsedThisTurn,
                'scheme_points' => $validated['scheme_points'],
                'points_scored' => $validated['strategy_points'] + $validated['scheme_points'],
            ]);

            $player->update([
                'total_points' => GameTurn::where('game_id', $game->id)
                    ->where('game_player_id', $player->id)
                    ->sum('points_scored'),
            ]);
        });

        $this->syncToTournamentGame($game->fresh()->load('players.master'));

        $this->broadcastToOpponents($game, new GameTurnAdvanced($game->fresh()));

        return response()->json(['success' => true, 'total_points' => $player->fresh()->total_points]);
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

        // The bonus is used if strategy_points = 2 (base + bonus) OR the
        // client flagged a 1-VP turn as the bonus-only case. Either signal
        // counts against the once-per-game cap.
        $bonusUsedThisTurn = $validated['strategy_points'] > 1 || ! empty($validated['strategy_bonus_used']);
        if ($bonusUsedThisTurn) {
            $bonusAlreadyUsed = $previousTurns->contains(
                fn (GameTurn $t) => $t->strategy_bonus_used || $t->strategy_points > 1,
            );
            if ($bonusAlreadyUsed) {
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
        $result = DB::transaction(function () use ($game, $player, $turnSchemeId, $schemeAction, $validated, $nextSchemeId, $totalTurnPoints, $crewSnapshot, $bonusUsedThisTurn) {
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
                    'strategy_bonus_used' => $bonusUsedThisTurn,
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

            $removed = [];
            $bothDone = $locked->players()->where('is_turn_complete', true)->count() === 2;
            if ($bothDone) {
                if ($locked->current_turn >= $locked->max_turns) {
                    $locked->players()->update(['is_game_complete' => true]);
                    $this->finalizeGame($locked);

                    return ['both_done' => true, 'game_complete' => true];
                }

                $locked->increment('current_turn');
                $locked->players()->update(['is_turn_complete' => false]);
                $removed = $this->removeEndOfTurnTokens($locked);
            }

            return ['both_done' => $bothDone, 'game_complete' => false, 'removed_tokens' => $removed];
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

        return response()->json([
            'success' => true,
            'both_done' => $result['both_done'],
            'removed_tokens' => $result['removed_tokens'] ?? [],
        ]);
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

        $this->broadcastToOpponents($game, new GameCrewMemberUpdated($game, 'cancel_complete'));

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

        if ($game->format === GameFormatEnum::Campaign) {
            $this->syncCampaignGameResult($game, $p1, $p2);
        }

        if (! $game->is_solo || $game->is_observable) {
            broadcast(new GameStatusChanged($game));
        }
    }

    /**
     * Live-tracked Campaign games (solo or multiplayer, both created via
     * CampaignGameController's playLive()/store()) never write their final
     * VP/winner back onto the linked CampaignGame row — unlike the
     * manually-logged solo path (storeSolo()), which fills these in at log
     * time. Without this, the campaign never learns the game finished, so
     * there was no Aftermath prefill to build from and no way to reach the
     * Aftermath wizard at all afterward.
     *
     * schemes_completed_a/b deliberately stay untouched — the live tracker
     * only totals points, it doesn't itemize "schemes completed" the way the
     * manual solo log form asks for directly, and Aftermath's own Phase 1
     * (drawHand()) takes that count as a fresh required input regardless, so
     * this is a pure prefill gap, not a functional one.
     */
    private function syncCampaignGameResult(Game $game, GamePlayer $p1, GamePlayer $p2): void
    {
        $campaignGame = CampaignGame::where('base_game_id', $game->id)->first();
        if (! $campaignGame) {
            return;
        }

        $campaignGame->loadMissing('crewA:id,user_id');
        $aIsP1 = $campaignGame->crewA->user_id === $p1->user_id;
        $pA = $aIsP1 ? $p1 : $p2;
        $pB = $aIsP1 ? $p2 : $p1;

        $campaignGame->update([
            'vp_a' => $pA->total_points,
            'vp_b' => $pB->total_points,
            'winner_crew_id' => match (true) {
                $pA->total_points > $pB->total_points => $campaignGame->crew_a_id,
                $pB->total_points > $pA->total_points => $campaignGame->crew_b_id,
                default => null,
            },
            'status' => 'aftermath',
        ]);
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
        $tg = TournamentGame::with(['round.tournament', 'playerOne', 'playerTwo'])->where('game_id', $game->id)->first();
        if (! $tg) {
            return;
        }
        if (in_array($tg->result, [TournamentGameResultEnum::Completed, TournamentGameResultEnum::Forfeited])) {
            return;
        }

        /** @var GamePlayer|null $slot1 */
        $slot1 = $game->players->firstWhere('slot', 1) ?? $game->players()->where('slot', 1)->first();
        /** @var GamePlayer|null $slot2 */
        $slot2 = $game->players->firstWhere('slot', 2) ?? $game->players()->where('slot', 2)->first();
        if (! $slot1 || ! $slot2) {
            return;
        }

        // Map tracker slots back to the tournament's playerOne / playerTwo by
        // user_id. TournamentTrackerGameFactory always puts the BiggerHat user
        // in tracker slot 1 for solo games — that means tracker slot 1 doesn't
        // necessarily correspond to TournamentGame.playerOne. Resolving by
        // user_id keeps VP flowing into the correct tournament-side column.
        $tpOneUserId = $tg->playerOne->user_id;
        $slot1MapsToTournamentOne = match (true) {
            $tpOneUserId !== null && $slot1->user_id === $tpOneUserId => true,
            $tpOneUserId === null && $slot1->user_id === null => true,
            default => false,
        };

        [$pOne, $pTwo] = $slot1MapsToTournamentOne ? [$slot1, $slot2] : [$slot2, $slot1];

        $turns = GameTurn::where('game_id', $game->id)
            ->whereIn('game_player_id', [$pOne->id, $pTwo->id])
            ->get()
            ->groupBy('game_player_id');
        $pOneTurns = $turns->get($pOne->id, collect());
        $pTwoTurns = $turns->get($pTwo->id, collect());

        $tg->update([
            'player_one_strategy_vp' => (int) $pOneTurns->sum('strategy_points'),
            'player_one_scheme_vp' => (int) $pOneTurns->sum('scheme_points'),
            'player_one_vp' => (int) $pOne->total_points,
            'player_two_strategy_vp' => (int) $pTwoTurns->sum('strategy_points'),
            'player_two_scheme_vp' => (int) $pTwoTurns->sum('scheme_points'),
            'player_two_vp' => (int) $pTwo->total_points,
            // Only back-fill master/title/faction when the TO hasn't entered them —
            // preserves manual corrections (typo fixes, alt title pick, etc.).
            //
            // The TO score dialog persists `title` as the full display_name
            // (e.g. "Nellie with a Past") so it round-trips through the Select
            // options keyed on display_name. Writing just Character.title
            // (the bare suffix) here left the Title dropdown blank on reopen.
            'player_one_master' => $tg->player_one_master ?: $pOne->master?->name,
            'player_one_title' => $tg->player_one_title ?: $pOne->master?->display_name,
            'player_one_faction' => $tg->player_one_faction ?: $pOne->getRawOriginal('faction'),
            'player_two_master' => $tg->player_two_master ?: $pTwo->master?->name,
            'player_two_title' => $tg->player_two_title ?: $pTwo->master?->display_name,
            'player_two_faction' => $tg->player_two_faction ?: $pTwo->getRawOriginal('faction'),
            'player_one_crew_build_id' => $tg->player_one_crew_build_id ?: $pOne->crew_build_id,
            'player_two_crew_build_id' => $tg->player_two_crew_build_id ?: $pTwo->crew_build_id,
        ]);

        // Broadcast so any open Manage/View page refreshes live.
        if ($tg->round && $tg->round->tournament) {
            broadcast(new TournamentUpdated($tg->round->tournament, 'tracker_synced'))->toOthers();
        }
    }
}
