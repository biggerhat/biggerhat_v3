<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\AdvancementTableEnum;
use App\Enums\BackAlleyDoctorOutcomeEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Campaign\CampaignLeaderXpTrack;
use App\Models\Campaign\CrewCardEffect;
use App\Models\Campaign\Equipment;
use App\Models\Campaign\Injury;
use App\Models\Campaign\SummoningAdvancement;
use App\Models\Campaign\Totem;
use App\Models\CustomCharacter;
use App\Services\CampaignRules;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Six-phase Aftermath wizard per rulebook pg 20–36:
 *
 *   1. Draw Aftermath Hand   — server snapshots a hand from the fate deck
 *   2. Payday                — auto-computes scrip from VP + win bonus + CR diff
 *   3. Barter                — equipment purchases (deferred: catalog data hand-typed)
 *   4. Advance Leader        — XP earn + spend (deferred: advancement picker UI)
 *   5. Back-Alley Doctor     — pay scrip per attempt to remove injury (deferred)
 *   6. Determine Injuries    — auto-flips per killed non-peon model
 *
 * Phases 3/4/5 ship as "advance only" stubs this iteration — the controller
 * accepts the advance request and increments current_phase without mutating
 * arsenal state. Players who hit those phases in Aftermath-without-Catalog-Data
 * scenarios should record the result manually for now.
 */
class CampaignAftermathController extends Controller
{
    use AuthorizesCampaignAccess;

    private const FATE_DECK_VALUES = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];

    private const FATE_DECK_SUITS = ['ram', 'crow', 'mask', 'tome'];

    public function start(Request $request, CampaignGame $campaignGame)
    {
        $this->ensureGameMember($request, $campaignGame);

        $crew = $this->crewFor($request, $campaignGame);

        $aftermath = CampaignAftermath::firstOrCreate(
            ['campaign_game_id' => $campaignGame->id, 'campaign_crew_id' => $crew->id],
            ['current_phase' => 1, 'status' => 'open'],
        );

        return redirect()->route('campaigns.aftermaths.show', $aftermath);
    }

    public function show(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        $aftermath->load([
            'campaignGame.campaign:id,name,status,current_week,length_weeks',
            'campaignGame.baseGame:id,uuid,name,encounter_size,season,format,status',
            'crew:id,share_code,name,faction,scrip,user_id',
        ]);

        return inertia('Campaigns/Aftermath', [
            'aftermath' => $aftermath,
            'is_owner' => $request->user()->id === $aftermath->crew->user_id,
            'killed_models' => $this->killedNonPeonModelsForCrew($aftermath),
            // Phase-gated lazy props. Inertia evaluates closures on full visits,
            // so gating on `current_phase` here avoids running large catalog
            // queries during phases that don't need them. UI gates rendering
            // on the same flag.
            'equipment_catalog' => fn () => $aftermath->current_phase === 3
                ? Equipment::query()
                    ->where('is_red_joker_entry', false)
                    ->orderBy('br')
                    ->orderBy('name')
                    ->get(['id', 'name', 'br', 'cc', 'is_always_available', 'ttw_only', 'pool_suit_a', 'pool_suit_b', 'body'])
                : null,
            'crew_injuries' => fn () => $aftermath->current_phase === 5
                ? DB::table('campaign_arsenal_model_injuries as ami')
                    ->join('campaign_arsenal_models as cam', 'cam.id', '=', 'ami.campaign_arsenal_model_id')
                    ->join('injury_catalog as ic', 'ic.id', '=', 'ami.injury_catalog_id')
                    ->join('characters as c', 'c.id', '=', 'cam.character_id')
                    ->where('cam.campaign_crew_id', $aftermath->campaign_crew_id)
                    ->whereNull('cam.annihilated_at')
                    ->select(
                        'ami.id as pivot_id',
                        'cam.id as arsenal_model_id',
                        'cam.label',
                        'c.display_name',
                        'ic.name as injury_name',
                    )
                    ->get()
                : null,
            // Phase 4 — Advance Leader support: current XP track + advancement catalogs.
            'xp_track' => fn () => $aftermath->current_phase === 4 ? $this->loadXpTrackForCrew($aftermath) : null,
            'advancement_catalogs' => fn () => $aftermath->current_phase === 4 ? [
                'attack_mod' => AdvancementAttackMod::orderBy('flip_value')->orderBy('name')->get(['id', 'name', 'body', 'flip_value', 'is_always_available', 'modifier_type', 'suit']),
                'tactical_mod' => AdvancementTacticalMod::orderBy('flip_value')->orderBy('name')->get(['id', 'name', 'body', 'flip_value', 'is_always_available', 'modifier_type', 'suit']),
                'action' => AdvancementAction::orderBy('flip_value')->orderBy('name')->get(['id', 'name', 'body', 'flip_value', 'is_always_available']),
                'ability' => AdvancementAbility::orderBy('flip_value')->orderBy('name')->get(['id', 'name', 'body', 'flip_value', 'is_always_available']),
                'totem' => Totem::orderBy('flip_value')->orderBy('name')->get(['id', 'name', 'flip_value', 'is_black_joker', 'is_red_joker']),
                'summoning' => SummoningAdvancement::orderBy('name')->get(['id', 'name', 'body']),
                'crew_card' => CrewCardEffect::orderBy('name')->get(['id', 'name', 'body']),
            ] : null,
        ]);
    }

    public function drawHand(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase !== 1 || $aftermath->hand_drawn) {
            return redirect()->back();
        }

        $data = $request->validate([
            'completed_without_withdrawing' => ['required', 'boolean'],
            'schemes_completed' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        $size = CampaignRules::aftermathHandSize(
            $data['completed_without_withdrawing'],
            $data['schemes_completed'],
        );

        $hand = [];
        for ($i = 0; $i < $size; $i++) {
            $hand[] = [
                'value' => self::FATE_DECK_VALUES[array_rand(self::FATE_DECK_VALUES)],
                'suit' => self::FATE_DECK_SUITS[array_rand(self::FATE_DECK_SUITS)],
                'is_joker' => false,
            ];
        }

        $advanced = $this->lockAndAdvance($aftermath, 1, function (CampaignAftermath $locked) use ($hand) {
            if ($locked->hand_drawn) {
                return;
            }
            $locked->update([
                'hand_drawn' => $hand,
                'current_phase' => 2,
            ]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage(sprintf('Drew %d card(s). On to Payday.', $size));
    }

    public function payday(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase !== 2) {
            return redirect()->back();
        }

        $data = $request->validate([
            'vp' => ['required', 'integer', 'min:0', 'max:30'],
            'won' => ['required', 'boolean'],
            'crew_cr' => ['required', 'integer'],
            'opponent_cr' => ['required', 'integer'],
        ]);

        $scrip = CampaignRules::scripFromGame(
            vp: $data['vp'],
            won: $data['won'],
            crewCr: $data['crew_cr'],
            opponentCr: $data['opponent_cr'],
        );

        $advanced = $this->lockAndAdvance($aftermath, 2, function (CampaignAftermath $locked) use ($scrip) {
            $locked->update([
                'scrip_earned' => $scrip,
                'current_phase' => 3,
            ]);
            $locked->crew()->increment('scrip', $scrip);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage("Earned {$scrip} scrip. On to Barter.");
    }

    /**
     * Phase 3 — Barter (pg 21–30). One flip determines which equipment the
     * crew may buy from the catalog. Items are eligible iff:
     *   - is_always_available, OR
     *   - br ≤ flip_value AND at least one of (pool_suit_a, pool_suit_b)
     *     matches one of the crew's two keywords' suit pools
     * Red joker triggers a Those Who Thirst sub-flip (deferred — store the
     * flag for now).
     *
     * Purchases deduct cc from crew.scrip; insufficient scrip is rejected.
     */
    public function barter(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase !== 3) {
            return redirect()->back();
        }

        $data = $request->validate([
            'flip_value' => ['required', 'integer', 'min:1', 'max:13'],
            'flip_suit' => ['nullable', 'string'],
            'is_red_joker' => ['required', 'boolean'],
            'purchases' => ['nullable', 'array'],
            'purchases.*' => ['integer', 'exists:equipment_catalog,id'],
        ]);

        $crew = $aftermath->crew;
        $purchaseIds = $data['purchases'] ?? [];

        // Eligibility check + cost tally.
        $items = Equipment::query()->whereIn('id', $purchaseIds)->get();
        $totalCc = (int) $items->sum('cc');
        $ineligible = [];
        foreach ($items as $eq) {
            $eligible = $eq->is_always_available || ($eq->br !== null && $eq->br <= $data['flip_value']);
            // Red joker enables ttw_only items; otherwise they're filtered out.
            if ($eq->ttw_only && ! $data['is_red_joker']) {
                $eligible = false;
            }
            if (! $eligible) {
                $ineligible[] = $eq->name;
            }
        }

        if (! empty($ineligible)) {
            return redirect()->back()->withMessage(
                'Some items are not barterable at flip '.$data['flip_value'].': '.implode(', ', $ineligible),
                null,
                MessageTypeEnum::error,
            );
        }

        if ($totalCc > $crew->scrip) {
            return redirect()->back()->withMessage(
                "Not enough scrip — needs {$totalCc}, have {$crew->scrip}.",
                null,
                MessageTypeEnum::error,
            );
        }

        $advanced = $this->lockAndAdvance($aftermath, 3, function (CampaignAftermath $locked) use ($crew, $items, $data, $totalCc) {
            foreach ($items as $eq) {
                CampaignEquipment::create([
                    'campaign_crew_id' => $crew->id,
                    'equipment_catalog_id' => $eq->id,
                    'source' => $eq->ttw_only ? 'joker' : 'barter',
                    'acquired_aftermath_id' => $locked->id,
                ]);
            }

            if ($totalCc > 0) {
                $crew->decrement('scrip', $totalCc);
            }

            DB::table('campaign_aftermath_barter')->insert([
                'campaign_aftermath_id' => $locked->id,
                'raw_flip_value' => $data['flip_value'],
                'raw_flip_suit' => $data['flip_suit'] ?? null,
                'cheated_to' => null,
                'purchases' => json_encode($items->pluck('id')->all()),
                'red_joker_ttw_flip_value' => $data['is_red_joker'] ? $data['flip_value'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $locked->update(['current_phase' => 4]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage('Bartered '.$items->count().' item(s) for '.$totalCc.' scrip.');
    }

    /**
     * Phase 5 — Back-Alley Doctor (pg 33). Per-injury attempt: pay 1 scrip,
     * flip, resolve to a doctor result, apply the outcome. The doctor keeps
     * the scrip regardless of outcome (pg 33 explicit).
     *
     * Outcomes:
     *   - no_effect: do nothing
     *   - removed: detach the injury pivot row
     *   - added_injury: insert a NEW injury (Oops — "attach an injury upgrade")
     *   - gained_undead / gained_construct: detach + add characteristic
     *   - lucky_miss_reflip: detach + remove, follow-up reflip on Lucky Miss
     *     table is deferred to next iteration
     */
    public function doctor(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase !== 5) {
            return redirect()->back();
        }

        // No Injuries optional rule (pg 146): Phase 5 (Doctor) + Phase 6
        // (Determine Injuries) are skipped. Lock the aftermath here.
        if ($this->skipInjuryPhases($aftermath)) {
            $this->lockAndAdvance($aftermath, 5, function (CampaignAftermath $locked) {
                $locked->update(['current_phase' => 7, 'status' => 'locked']);
            });

            return redirect()->route('campaigns.crews.arsenal.show', [
                $aftermath->campaignGame->campaign_id, $aftermath->crew->share_code,
            ])->withMessage('No Injuries optional rule — Phases 5 + 6 skipped, aftermath complete.');
        }

        $data = $request->validate([
            'attempts' => ['nullable', 'array'],
            // Validate existence here so we can fail fast on bogus IDs rather
            // than silently `continue` past them later — otherwise a player
            // could drain their own scrip on non-resolving attempts.
            'attempts.*.injury_pivot_id' => ['required', 'integer', 'exists:campaign_arsenal_model_injuries,id'],
            'attempts.*.flip_value' => ['required', 'integer', 'min:1', 'max:13'],
            'attempts.*.suit_pool' => ['required', 'string', 'in:pc,te'],
            'attempts.*.cheated' => ['nullable', 'boolean'],
        ]);

        $attempts = $data['attempts'] ?? [];
        $crew = $aftermath->crew;
        $scripCost = count($attempts);

        // Ownership check on every targeted injury pivot. Cross-crew references
        // are rejected up front rather than silently dropped — otherwise the
        // scrip cost gets charged for attempts that never actually fire.
        if (! empty($attempts)) {
            $pivotIds = array_map(fn ($a) => (int) $a['injury_pivot_id'], $attempts);
            $ownedCount = DB::table('campaign_arsenal_model_injuries as ami')
                ->join('campaign_arsenal_models as cam', 'cam.id', '=', 'ami.campaign_arsenal_model_id')
                ->whereIn('ami.id', $pivotIds)
                ->where('cam.campaign_crew_id', $crew->id)
                ->count();
            if ($ownedCount !== count($pivotIds)) {
                return redirect()->back()->withMessage(
                    'One or more doctor attempts target an injury that does not belong to your crew.',
                    null,
                    MessageTypeEnum::error,
                );
            }
        }

        if ($scripCost > $crew->scrip) {
            return redirect()->back()->withMessage(
                "Not enough scrip — doctor attempts cost {$scripCost}, have {$crew->scrip}.",
                null,
                MessageTypeEnum::error,
            );
        }

        $advanced = $this->lockAndAdvance($aftermath, 5, function (CampaignAftermath $locked) use ($crew, $attempts, $scripCost) {
            foreach ($attempts as $attempt) {
                $pivot = DB::table('campaign_arsenal_model_injuries')
                    ->where('id', $attempt['injury_pivot_id'])
                    ->first();
                if (! $pivot) {
                    continue;
                }
                $model = CampaignArsenalModel::query()->whereKey($pivot->campaign_arsenal_model_id)->first();
                if (! $model || $model->campaign_crew_id !== $crew->id) {
                    continue;
                }

                $result = BackAlleyDoctorResult::query()
                    ->where(function ($q) use ($attempt) {
                        $q->where('flip_value_min', '<=', $attempt['flip_value'])
                            ->where('flip_value_max', '>=', $attempt['flip_value']);
                    })
                    ->first();

                $outcome = $result ? $result->outcome_kind : BackAlleyDoctorOutcomeEnum::NoEffect;

                $removesInjury = in_array($outcome, [
                    BackAlleyDoctorOutcomeEnum::Removed,
                    BackAlleyDoctorOutcomeEnum::GainedUndead,
                    BackAlleyDoctorOutcomeEnum::GainedConstruct,
                    BackAlleyDoctorOutcomeEnum::LuckyMissReflip,
                ], true);
                if ($removesInjury) {
                    DB::table('campaign_arsenal_model_injuries')->where('id', $pivot->id)->delete();
                }
                // AddedInjury (Oops) and NoEffect leave the original injury attached.

                DB::table('campaign_aftermath_doctor')->insert([
                    'campaign_aftermath_id' => $locked->id,
                    'target_arsenal_model_id' => $model->id,
                    'target_injury_id' => $pivot->id,
                    'flip_value' => $attempt['flip_value'],
                    'cheated' => (bool) ($attempt['cheated'] ?? false),
                    'outcome' => $outcome->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($scripCost > 0) {
                $crew->decrement('scrip', $scripCost);
            }

            $locked->update(['current_phase' => 6]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage('Doctor attempts resolved ('.$scripCost.' scrip spent).');
    }

    /**
     * Skip the current phase with a "no action this game" annotation. Every
     * phase has its own dedicated handler now (draw-hand, payday, barter,
     * advance-leader, doctor, determine-injuries), so this endpoint is the
     * intentional skip path — the player declares "nothing to record" for
     * this phase.
     *
     * We log the skip on `hand_used` so the aftermath history shows the
     * choice rather than silently advancing.
     */
    public function advance(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase < 1 || $aftermath->current_phase >= 6) {
            return redirect()->back();
        }

        $this->lockAndAdvance($aftermath, $aftermath->current_phase, function (CampaignAftermath $locked) {
            $skipped = $locked->hand_used ?? [];
            $skipped[] = [
                'phase' => $locked->current_phase,
                'used_for' => 'skip',
                'notes' => 'Player intentionally skipped this phase.',
                'at' => now()->toISOString(),
            ];

            $locked->update([
                'current_phase' => $locked->current_phase + 1,
                'hand_used' => $skipped,
            ]);
        });

        return redirect()->route('campaigns.aftermaths.show', $aftermath);
    }

    /**
     * Phase 4 — Advance Leader (pg 31–32). Two things happen:
     *
     *   1. XP earned this game (cap 3 per CampaignRules::xpFromGame) fills the
     *      next N unfilled boxes on the Leadership Experience track.
     *   2. For each numbered box reached, the player picks an advancement
     *      (table + catalog row). We write a `campaign_leader_advancements`
     *      row per pick; the renderer composes the final action/ability list
     *      by walking these.
     *
     * This iteration accepts the picks at face value — full flip-value validation
     * + auto action-attachment for triggers/Skl-boosts are deferred until the
     * catalog data is fully entered and the picker UI can preview the
     * composed Leader card.
     */
    public function advanceLeader(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase !== 4) {
            return redirect()->back();
        }

        $data = $request->validate([
            'xp_earned' => ['required', 'integer', 'min:0', 'max:3'],
            'advancements' => ['nullable', 'array'],
            'advancements.*.source_table' => ['required', 'string', Rule::enum(AdvancementTableEnum::class)],
            'advancements.*.catalog_id' => ['nullable', 'integer'],
            'advancements.*.applied_to_action_index' => ['nullable', 'integer'],
            'advancements.*.position_in_xp_track' => ['required', 'integer', 'min:0', 'max:26'],
            'advancements.*.free_choice' => ['nullable', 'array'],
        ]);

        $leader = CustomCharacter::query()
            ->where('campaign_crew_id', $aftermath->campaign_crew_id)
            ->where('is_campaign_leader', true)
            ->where('current', true)
            ->first();

        if (! $leader) {
            return redirect()->back()->withMessage(
                'No active leader for this crew — build one first.',
                null,
                MessageTypeEnum::error,
            );
        }

        $advanced = $this->lockAndAdvance($aftermath, 4, function (CampaignAftermath $locked) use ($leader, $data) {
            // Lazy-init the XP track to the canonical 27-box layout the first
            // time we touch it.
            $xp = CampaignLeaderXpTrack::firstOrCreate(
                ['custom_character_id' => $leader->id],
                ['track' => CampaignLeaderXpTrack::defaultTrack()],
            );

            $track = $xp->track;
            $toFill = (int) $data['xp_earned'];
            foreach ($track as $i => $box) {
                if ($toFill <= 0) {
                    break;
                }
                if (! $box['filled']) {
                    $track[$i]['filled'] = true;
                    $toFill--;
                }
            }
            $xp->update(['track' => $track]);

            foreach (($data['advancements'] ?? []) as $a) {
                CampaignLeaderAdvancement::create([
                    'custom_character_id' => $leader->id,
                    'source_aftermath_id' => $locked->id,
                    'source_table' => $a['source_table'],
                    'catalog_id' => $a['catalog_id'] ?? null,
                    'applied_to_action_index' => $a['applied_to_action_index'] ?? -1,
                    'position_in_xp_track' => $a['position_in_xp_track'],
                    'free_choice' => $a['free_choice'] ?? null,
                    'acquired_at' => now(),
                ]);
            }

            $locked->update(['current_phase' => 5]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage("Advanced Leader: {$data['xp_earned']} XP, ".count($data['advancements'] ?? []).' advancement(s).');
    }

    /**
     * Phase 6 — Determine Injuries. Accepts a list of `{ model_id, flip_value,
     * suit }` from the client (which flipped the deck) and writes injury rows.
     * Annihilation logic: if a model has 3+ injuries after this phase, mark it
     * annihilated.
     */
    public function determineInjuries(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        if ($aftermath->current_phase !== 6) {
            return redirect()->back();
        }

        $data = $request->validate([
            'flips' => ['nullable', 'array'],
            'flips.*.arsenal_model_id' => ['required', 'integer', 'exists:campaign_arsenal_models,id'],
            'flips.*.flip_value' => ['required', 'integer', 'min:1', 'max:13'],
            'flips.*.suit_pool' => ['required', 'string', 'in:pc,te'],
        ]);

        $flips = $data['flips'] ?? [];

        $this->lockAndAdvance($aftermath, 6, function (CampaignAftermath $locked) use ($flips) {
            foreach ($flips as $f) {
                $injury = Injury::query()
                    ->where('suit_pool', $f['suit_pool'])
                    ->where('flip_value', $f['flip_value'])
                    ->first();

                if (! $injury) {
                    continue;
                }

                // Skip purely-cosmetic "Just a Flesh Wound" rows that don't
                // actually attach an injury.
                $attaches = ! str_contains(strtolower($injury->name), 'flesh wound');

                // Pessimistic lock on the arsenal model row so that the
                // insert + count + annihilation update sequence below is
                // atomic against concurrent determineInjuries submissions
                // on the same model (otherwise two concurrent flips could
                // both observe count < 3 and skip annihilation).
                $model = CampaignArsenalModel::query()
                    ->whereKey($f['arsenal_model_id'])
                    ->lockForUpdate()
                    ->first();
                if (! $model || $model->campaign_crew_id !== $locked->campaign_crew_id) {
                    continue;
                }

                if ($attaches && ! $injury->annihilates_model) {
                    DB::table('campaign_arsenal_model_injuries')->insert([
                        'campaign_arsenal_model_id' => $model->id,
                        'injury_catalog_id' => $injury->id,
                        'acquired_aftermath_id' => $locked->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $count = DB::table('campaign_arsenal_model_injuries')
                        ->where('campaign_arsenal_model_id', $model->id)
                        ->count();

                    if ($count >= 3) {
                        $model->update(['annihilated_at' => now()]);
                    }
                }

                if ($injury->annihilates_model) {
                    $model->update(['annihilated_at' => now()]);
                }
            }

            $locked->update([
                'current_phase' => 6,
                'status' => 'locked',
            ]);
        });

        return redirect()->route('campaigns.crews.arsenal.show', [
            $aftermath->campaignGame->campaign_id, $aftermath->crew->share_code,
        ])->withMessage('Aftermath complete.');
    }

    public function finalize(Request $request, CampaignAftermath $aftermath)
    {
        $this->ensureAftermathOwner($request, $aftermath);

        $aftermath->update(['status' => 'locked']);

        return redirect()->route('campaigns.crews.arsenal.show', [
            $aftermath->campaignGame->campaign_id, $aftermath->crew->share_code,
        ])->withMessage('Aftermath closed.');
    }

    private function killedNonPeonModelsForCrew(CampaignAftermath $aftermath)
    {
        // Auto-detect via GameCrewMember death events from the wrapping game.
        // Falls back to "all active non-peon arsenal models" when there's no
        // linked base game yet — useful when testing aftermath flows in
        // isolation, and a sane default if a campaign game wraps without a
        // tracker run.
        $baseGameId = $aftermath->campaignGame->base_game_id ?? null;

        if (! $baseGameId) {
            return CampaignArsenalModel::query()
                ->where('campaign_crew_id', $aftermath->campaign_crew_id)
                ->active()
                ->where('is_peon', false)
                ->with('character:id,display_name,station,faction')
                ->get(['id', 'campaign_crew_id', 'character_id', 'label']);
        }

        $killedCharacterIds = DB::table('game_crew_members')
            ->where('game_id', $baseGameId)
            ->where('is_killed', true)
            ->whereNotNull('character_id')
            ->pluck('character_id')
            ->all();

        if (empty($killedCharacterIds)) {
            return collect();
        }

        return CampaignArsenalModel::query()
            ->where('campaign_crew_id', $aftermath->campaign_crew_id)
            ->active()
            ->where('is_peon', false)
            ->whereIn('character_id', $killedCharacterIds)
            ->with('character:id,display_name,station,faction')
            ->get(['id', 'campaign_crew_id', 'character_id', 'label']);
    }

    private function loadXpTrackForCrew(CampaignAftermath $aftermath): ?array
    {
        $leader = CustomCharacter::query()
            ->where('campaign_crew_id', $aftermath->campaign_crew_id)
            ->where('is_campaign_leader', true)
            ->where('current', true)
            ->first();

        if (! $leader) {
            return null;
        }

        $xp = CampaignLeaderXpTrack::firstOrCreate(
            ['custom_character_id' => $leader->id],
            ['track' => CampaignLeaderXpTrack::defaultTrack()],
        );

        return [
            'leader_id' => $leader->id,
            'leader_name' => $leader->name,
            'tag' => $leader->tag,
            'track' => $xp->track,
        ];
    }

    /**
     * Run $work inside a transaction with a pessimistic lock on the aftermath
     * row, re-checking `current_phase` against $expectedPhase after the lock.
     *
     * Guards against double-clicks / concurrent advances racing past the early
     * `if ($aftermath->current_phase !== X) return back();` check at the top
     * of each phase handler. The closure runs only if the lock confirms the
     * expected phase; returns whether it executed so the controller can branch
     * on "stale request — just redirect back" vs "advanced successfully."
     *
     * @param  \Closure(CampaignAftermath): void  $work
     */
    private function lockAndAdvance(CampaignAftermath $aftermath, int $expectedPhase, \Closure $work): bool
    {
        $advanced = false;
        DB::transaction(function () use ($aftermath, $expectedPhase, $work, &$advanced) {
            $locked = CampaignAftermath::query()
                ->whereKey($aftermath->id)
                ->lockForUpdate()
                ->first();

            // Locked aftermaths are terminal — refuse all further mutations
            // even if a stale handler thinks it's still at the right phase.
            if (! $locked || $locked->current_phase !== $expectedPhase || $locked->status !== 'open') {
                return;
            }

            $work($locked);
            $advanced = true;
        });

        return $advanced;
    }

    /**
     * Whether the campaign has the No Injuries optional rule enabled (pg 146).
     */
    private function skipInjuryPhases(CampaignAftermath $aftermath): bool
    {
        $rules = $aftermath->campaignGame->campaign->optional_rules ?? [];

        return (bool) ($rules['no_injuries'] ?? false);
    }

    private function crewFor(Request $request, CampaignGame $campaignGame): CampaignCrew
    {
        $userId = $request->user()->id;
        $crew = CampaignCrew::query()
            ->where('campaign_id', $campaignGame->campaign_id)
            ->where('user_id', $userId)
            ->first();

        if (! $crew) {
            abort(403);
        }

        return $crew;
    }
}
