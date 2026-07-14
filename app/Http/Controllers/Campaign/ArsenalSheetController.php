<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModelInjury;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Campaign\LuckyMiss;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Services\CampaignRules;
use App\Support\Campaign\AftermathCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Renders a crew's Arsenal Sheet — the canonical public artifact described on
 * pg 56 of Index of the Untold. Two entry points:
 *
 *   /campaigns/{campaign}/crews/{crew}              — authed, requires membership
 *   /a/{share_code}                                  — public read-only, no auth
 *
 * The Vue layer is the same; the controller just toggles `is_owner` / `is_member`
 * flags so edit affordances stay hidden on the public path.
 */
class ArsenalSheetController extends Controller
{
    public function show(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        if ($crew->campaign_id !== $campaign->id) {
            abort(404);
        }

        $user = $request->user();
        $isMember = $user && (
            $user->hasRole('super_admin')
            || $campaign->players()->where('user_id', $user->id)->exists()
        );

        if (! $isMember) {
            abort(403);
        }

        return $this->render($campaign, $crew, isMember: true, isOwner: $user && $user->id === $crew->user_id);
    }

    /**
     * Public share — anyone with the share_code can view. Still gated by
     * `campaign.access` upstream so the page stays hidden while pre-release.
     */
    public function share(Request $request, string $shareCode)
    {
        $crew = CampaignCrew::query()->where('share_code', $shareCode)->firstOrFail();
        $campaign = $crew->campaign;

        return $this->render($campaign, $crew, isMember: false, isOwner: false);
    }

    private function render(Campaign $campaign, CampaignCrew $crew, bool $isMember, bool $isOwner)
    {
        // Single load picks up leader + totem via the dedicated relations on
        // CampaignCrew; both hit the composite (campaign_crew_id, flag, current)
        // index so two narrow queries replace the prior two open ones.
        $crew->load([
            'leader',
            'totem',
            // Load full action + trigger data so ActionCard/AbilityCard can render properly.
            'crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
            'crewCardEffect.abilities',
            // Tier-4 borrowed effects (pg 32, 54) — stack alongside the starter.
            'crewCardAdvancements.crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
            'crewCardAdvancements.crewCardEffect.abilities',
            // Keywords have no faction column — the crew's faction is separate.
            'keywordOne:id,name',
            'keywordTwo:id,name',
            'arsenalModels' => fn ($q) => $q->active()->with([
                'character:id,slug,display_name,cost,faction,station',
                // First standard miniature provides the card image for CharacterCardView.
                'character.standardMiniatures:id,display_name,front_image,back_image,character_id,slug',
                'injuries.injury:id,name,description',
                // Real Abilities gained permanently outside the base
                // Character catalog row (currently only via Lucky Miss, pg 36).
                'gainedAbilities:id,name,description',
            ]),
        ]);

        // Propagate crew card action pivot signature flag so `is_signature` on
        // each serialized action reflects the crew-card-specific value.
        $crew->crewCardEffect?->actions->each(
            fn ($a) => $a->is_signature = (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
        );
        $crew->crewCardAdvancements->each(
            fn ($adv) => $adv->crewCardEffect->actions->each(
                fn ($a) => $a->is_signature = (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
            ),
        );

        // Resolve gained Lucky Miss ids to names for display.
        $luckyMissNames = LuckyMiss::query()->pluck('name', 'id');

        $leader = $crew->leader;
        $totem = $crew->totem;

        // Leader/Totem injuries (pg 33-34) — same table as arsenal model
        // injuries, keyed by custom_character_id instead. Neither
        // CustomCharacter has an `injuries` array in its own columns, so this
        // rides along as a separate attribute rather than a raw relation dump.
        $leaderTotemIds = array_filter([$leader?->id, $totem?->id]);
        if (! empty($leaderTotemIds)) {
            $injuriesByCharacter = CampaignArsenalModelInjury::query()
                ->whereIn('custom_character_id', $leaderTotemIds)
                ->with('injury:id,name,description')
                ->get()
                ->groupBy('custom_character_id');

            $leader?->setAttribute(
                'injury_names',
                ($injuriesByCharacter->get($leader->id) ?? collect())->map(fn ($i) => $this->shapeInjury($i))->filter()->values()->all(),
            );
            $totem?->setAttribute(
                'injury_names',
                ($injuriesByCharacter->get($totem->id) ?? collect())->map(fn ($i) => $this->shapeInjury($i))->filter()->values()->all(),
            );
        }

        // Campaign Rating (pg 19): equipment + leader/totem advancements − injuries.
        // All three counters come from the consolidated post-refactor sources:
        // campaign_equipment, campaign_leader_advancements, campaign_arsenal_model_injuries.
        $equipmentCount = $crew->activeEquipmentCount();
        $advancementCount = $crew->activeLeaderAdvancementCount();
        $injuryCount = $crew->activeInjuryCount();
        $cr = CampaignRules::campaignRating($equipmentCount, $advancementCount, $injuryCount);

        return inertia('Campaigns/ArsenalSheet', [
            'campaign' => $campaign->only(['id', 'name', 'status', 'length_weeks', 'current_week']),
            'crew' => array_merge(
                $crew->only(['id', 'share_code', 'name', 'faction', 'scrip', 'total_wins', 'crew_card_choice', 'crew_card_front_image']),
                [
                    'keyword_one' => $crew->keywordOne,
                    'keyword_two' => $crew->keywordTwo,
                    // Explicit mapping: the CampaignCrewCard model column is
                    // `description`, but the Vue layer (and all display components)
                    // expect `body` for the card's main text. Abilities/actions are
                    // serialized as loaded relations; their `description` column
                    // matches what ActionCard/AbilityCard expect.
                    'crew_card_effect' => $crew->crewCardEffect
                        ? array_merge($crew->crewCardEffect->toArray(), ['body' => $crew->crewCardEffect->description])
                        : null,
                    // Tier-4 borrowed effects (pg 32, 54) — stack alongside the starter.
                    'crew_card_advancements' => $crew->crewCardAdvancements->map(fn ($adv) => [
                        'id' => $adv->id,
                        'effect' => $adv->crewCardEffect
                            ? array_merge($adv->crewCardEffect->toArray(), ['body' => $adv->crewCardEffect->description])
                            : null,
                    ])->all(),
                    'arsenal_models' => $crew->arsenalModels->map(fn ($m) => [
                        'id' => $m->id,
                        'character_id' => $m->character_id,
                        'label' => $m->label,
                        'is_peon' => $m->is_peon,
                        'ignored_for_limits' => $m->ignored_for_limits,
                        'acquired_via' => $m->acquired_via,
                        'character' => $m->character ? [
                            ...$m->character->only(['id', 'slug', 'display_name', 'cost', 'faction', 'station']),
                            'standard_miniature' => $m->character->standardMiniatures->first()?->only(['id', 'display_name', 'front_image', 'back_image', 'character_id', 'slug']),
                        ] : null,
                        'injuries' => $m->injuries->map(fn ($i) => $this->shapeInjury($i))->filter()->values()->all(),
                        'gained_characteristics' => $m->gained_characteristics ?? [],
                        'lucky_miss' => collect($m->gained_lucky_miss_ids ?? [])
                            ->map(fn ($id) => $luckyMissNames[$id] ?? null)
                            ->filter()
                            ->values(),
                        // Real Abilities gained permanently from a Lucky Miss
                        // result (pg 36) — additive to the model's base Character abilities.
                        'gained_abilities' => $m->gainedAbilities->map(fn (Ability $a) => $this->shapeGainedAbility($a))->values()->all(),
                    ]),
                ],
            ),
            'leader' => $leader,
            // Resolved Leadership Experience track (pg 31) — filled boxes come
            // from logged-game XP via the Aftermath flow. Falls back to the
            // empty canonical layout so a freshly built leader still renders.
            'leader_xp_track' => $leader ? ($leader->xp_track ?? CustomCharacter::defaultXpTrack()) : null,
            // Advancements already logged against this leader, keyed for display
            // against the catalog (resolved to names client-side).
            'leader_advancements' => $leader
                ? CampaignLeaderAdvancement::query()
                    ->where('custom_character_id', $leader->id)
                    ->orderBy('position_in_xp_track')
                    ->get()
                    ->map(function (CampaignLeaderAdvancement $a) use ($crew) {
                        // Any Joker (Action/Ability, pg 49/51) free-picks an
                        // ally's own action/ability — resolve the ally's name
                        // so the log reads as "from Rusty Alyce", not a bare id.
                        $freeChoiceSourceName = null;
                        $sourceCharacterId = $a->free_choice['source_character_id'] ?? null;
                        if ($sourceCharacterId && in_array($a->source_table, [AdvancementTableEnum::Action, AdvancementTableEnum::Ability], true)) {
                            $freeChoiceSourceName = Character::query()->whereKey($sourceCharacterId)->value('display_name');
                        }

                        // Tier-4 Crew Card (pg 32, 54) — resolve the effect's
                        // own name. `advancement_catalogs.crew_card` excludes effects
                        // this crew already holds (so the same effect can't be
                        // picked twice), so once an effect is taken, the
                        // client-side catalog lookup used to resolve every
                        // other table's name can no longer find it — resolve
                        // it here instead, from the already-eager-loaded
                        // `crewCardAdvancements` bookkeeping row. catalog_core_id
                        // === the CampaignCrewCard id for this table (see
                        // LeaderAdvancementService::resolveCoreCatalogId()).
                        $heldCrewCard = $a->source_table === AdvancementTableEnum::CrewCard
                            ? $crew->crewCardAdvancements->firstWhere('crew_card_effect_id', $a->catalog_core_id)
                            : null;

                        return [
                            'id' => $a->id,
                            'position_in_xp_track' => $a->position_in_xp_track,
                            'source_table' => $a->source_table->value,
                            'catalog_id' => $a->catalog_core_id,
                            'free_choice' => $a->free_choice,
                            'free_choice_source_name' => $freeChoiceSourceName,
                            'crew_card_name' => $heldCrewCard?->crewCardEffect?->name,
                            'applied_to_custom_character_id' => $a->applied_to_custom_character_id,
                            'applied_to_action_index' => $a->applied_to_action_index,
                            'from_equipment_id' => $a->from_equipment_id,
                            'acquired_at' => $a->acquired_at,
                        ];
                    })
                : [],
            // Advancement-table catalogs (same source the Aftermath uses) — used
            // to resolve taken-advancement names for everyone and to drive the
            // owner's pick-an-advancement UI.
            'advancement_catalogs' => $leader ? AftermathCatalog::advancementCatalogs($crew) : null,
            'crew_card_choice_options' => $leader ? AftermathCatalog::crewCardChoiceOptions($crew) : null,
            'totem' => $totem,
            // The crew's earned equipment (pg 20 Barter) — attachable to any
            // model when hiring. Shown on the arsenal sheet below the models,
            // and selectable as an Attack/Tactical Mod advancement target (pg 31).
            'equipment' => AftermathCatalog::ownedEquipment($crew, $leader),
            'campaign_rating' => [
                'value' => $cr,
                'equipment_count' => $equipmentCount,
                'advancement_count' => $advancementCount,
                'injury_count' => $injuryCount,
            ],
            // Optional per-game journal entries (not a rules mechanic) —
            // written at the end of the Aftermath's Log Game flow, shown here
            // in chronological order, each with an auto-computed tally of
            // what actually changed that week (pulled from tables that are
            // already per-aftermath-linked — no new schema needed).
            'story_log' => $this->storyLog($crew),
            'view_mode' => [
                'is_member' => $isMember,
                'is_owner' => $isOwner,
                'share_url' => route('campaigns.crews.arsenal.share', $crew->share_code),
            ],
        ]);
    }

    /**
     * Shapes an injury pivot's loaded `injury` relation for display — the
     * description is what lets the Arsenal Sheet's injury badges open a
     * "what does this do" viewer instead of just showing the bare name.
     *
     * @return array{id: int, name: string, description: string|null}|null
     */
    private function shapeInjury(CampaignArsenalModelInjury $pivot): ?array
    {
        $injury = $pivot->injury;
        if (! $injury) {
            return null;
        }

        return ['id' => $injury->id, 'name' => $injury->name, 'description' => $injury->description];
    }

    /**
     * @return array{id: int, name: string, description: string|null}
     */
    private function shapeGainedAbility(Ability $ability): array
    {
        return ['id' => $ability->id, 'name' => $ability->name, 'description' => $ability->description];
    }

    /**
     * Optional per-game journal (not a rules mechanic) — written at the end
     * of the Aftermath's Log Game flow, shown chronologically. Each entry
     * gets an auto-computed tally of what changed that week — injuries,
     * Back-Alley Doctor attempts/outcomes, Lucky Misses, and Those Who
     * Thirst pickups — all pulled from tables that already carry a
     * `campaign_aftermath_id`/`acquired_aftermath_id` link, so no new schema
     * is needed. Batched (not per-row) to avoid an N+1 across every week.
     *
     * @return array<int, array<string, mixed>>
     */
    private function storyLog(CampaignCrew $crew): array
    {
        $aftermaths = CampaignAftermath::query()
            ->where('campaign_crew_id', $crew->id)
            ->whereNotNull('story_entry')
            ->with('campaignGame:id,week_number,crew_a_id,crew_b_id')
            ->orderBy('created_at')
            ->get();

        $aftermathIds = $aftermaths->pluck('id');

        $injuryCounts = CampaignArsenalModelInjury::query()
            ->whereIn('acquired_aftermath_id', $aftermathIds)
            ->selectRaw('acquired_aftermath_id, count(*) as total')
            ->groupBy('acquired_aftermath_id')
            ->pluck('total', 'acquired_aftermath_id');

        $doctorRows = DB::table('campaign_aftermath_doctor')
            ->whereIn('campaign_aftermath_id', $aftermathIds)
            ->get(['campaign_aftermath_id', 'outcome']);
        $doctorCounts = $doctorRows->countBy('campaign_aftermath_id');
        $luckyMissCounts = $doctorRows->where('outcome', 'lucky_miss_reflip')->countBy('campaign_aftermath_id');

        $ttwCounts = CampaignEquipment::query()
            ->whereIn('acquired_aftermath_id', $aftermathIds)
            ->where('source', 'joker')
            ->selectRaw('acquired_aftermath_id, count(*) as total')
            ->groupBy('acquired_aftermath_id')
            ->pluck('total', 'acquired_aftermath_id');

        return $aftermaths->map(fn (CampaignAftermath $a) => [
            'id' => $a->id,
            'week_number' => $a->campaignGame->week_number,
            'story_entry' => $a->story_entry,
            'created_at' => $a->created_at,
            'tally' => [
                'injuries' => (int) ($injuryCounts[$a->id] ?? 0),
                'doctor_attempts' => (int) ($doctorCounts[$a->id] ?? 0),
                'lucky_misses' => (int) ($luckyMissCounts[$a->id] ?? 0),
                'ttw_pickups' => (int) ($ttwCounts[$a->id] ?? 0),
            ],
        ])->all();
    }
}
