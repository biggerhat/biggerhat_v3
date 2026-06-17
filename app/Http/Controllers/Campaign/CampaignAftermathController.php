<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\CustomCharacter;
use App\Models\Trigger;
use App\Models\Upgrade;
use App\Services\CampaignRules;
use App\Services\FateDeck;
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

    public function start(Request $request, CampaignGame $campaignGame)
    {
        $this->ensureGameMember($request, $campaignGame);

        $crew = $this->crewFor($request, $campaignGame);

        // Strategic Withdrawal on turn ≤ 2 (pg 20): crew gets no VP, no
        // barter, no hand, no payday — they skip the entire aftermath EXCEPT
        // the injury flip. Jump them straight to Phase 6.
        $initialPhase = ($campaignGame->withdrew_crew_id === $crew->id
            && $campaignGame->withdrew_turn !== null
            && $campaignGame->withdrew_turn <= 2)
            ? 6
            : 1;

        $aftermath = CampaignAftermath::firstOrCreate(
            ['campaign_game_id' => $campaignGame->id, 'campaign_crew_id' => $crew->id],
            ['current_phase' => $initialPhase, 'status' => 'open'],
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
            // on the same flag. PHPStan covariance complaints on the closure
            // returns are ignored via phpstan.neon path rule.
            'equipment_catalog' => fn () => $aftermath->current_phase === 3
                ? Upgrade::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_upgrade_kind', 'equipment')
                    ->where('campaign_is_red_joker_entry', false)
                    ->orderBy('campaign_br')
                    ->orderBy('name')
                    ->get()
                    // Preserve the Vue payload shape — old keys map to new
                    // campaign_* columns. Lets the frontend stay unchanged.
                    ->map(fn (Upgrade $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'br' => $u->campaign_br,
                        'cc' => $u->campaign_cc,
                        'is_always_available' => (bool) $u->campaign_is_always_available,
                        'ttw_only' => (bool) $u->campaign_ttw_only,
                        'pool_suit_a' => $u->campaign_pool_suit_a,
                        'pool_suit_b' => $u->campaign_pool_suit_b,
                        'body' => $u->description,
                    ])
                : null,
            'crew_injuries' => fn () => $aftermath->current_phase === 5
                ? DB::table('campaign_arsenal_model_injuries as ami')
                    ->join('campaign_arsenal_models as cam', 'cam.id', '=', 'ami.campaign_arsenal_model_id')
                    // Phase 2 migration populated `injury_upgrade_id` on the pivot
                    // to point at the new core `upgrades` table.
                    ->join('upgrades as u', 'u.id', '=', 'ami.injury_upgrade_id')
                    ->join('characters as c', 'c.id', '=', 'cam.character_id')
                    ->where('cam.campaign_crew_id', $aftermath->campaign_crew_id)
                    ->whereNull('cam.annihilated_at')
                    ->select(
                        'ami.id as pivot_id',
                        'cam.id as arsenal_model_id',
                        'cam.label',
                        'c.display_name',
                        'u.name as injury_name',
                    )
                    ->get()
                : null,
            // Phase 4 — Advance Leader support: current XP track + advancement catalogs.
            'xp_track' => fn () => $aftermath->current_phase === 4 ? $this->loadXpTrackForCrew($aftermath) : null,
            'advancement_catalogs' => fn () => $aftermath->current_phase === 4 ? [
                // Attack/tactical mods live on the `triggers` table with
                // campaign_advancement_kind. Same shape returned to keep Vue
                // payload stable.
                'attack_mod' => Trigger::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_advancement_kind', 'attack')
                    ->orderBy('campaign_flip_value')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Trigger $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'body' => $t->description,
                        'flip_value' => $t->campaign_flip_value,
                        'is_always_available' => (bool) $t->campaign_is_always_available,
                        'modifier_type' => $t->campaign_modifier_type,
                        'suit' => $t->suits,
                    ]),
                'tactical_mod' => Trigger::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_advancement_kind', 'tactical')
                    ->orderBy('campaign_flip_value')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Trigger $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'body' => $t->description,
                        'flip_value' => $t->campaign_flip_value,
                        'is_always_available' => (bool) $t->campaign_is_always_available,
                        'modifier_type' => $t->campaign_modifier_type,
                        'suit' => $t->suits,
                    ]),
                'action' => Action::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_advancement_kind', 'action')
                    ->orderBy('campaign_flip_value')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Action $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'body' => $a->description,
                        'flip_value' => $a->campaign_flip_value,
                        'is_always_available' => (bool) $a->campaign_is_always_available,
                    ]),
                'ability' => Ability::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->orderBy('campaign_flip_value')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Ability $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'body' => $a->description,
                        'flip_value' => $a->campaign_flip_value,
                        'is_always_available' => (bool) $a->campaign_is_always_available,
                    ]),
                // Totems are now CustomCharacter template rows.
                'totem' => CustomCharacter::query()
                    ->where('is_campaign_totem_template', true)
                    ->orderBy('campaign_totem_flip_value')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (CustomCharacter $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'flip_value' => $c->campaign_totem_flip_value,
                        'is_black_joker' => (bool) $c->campaign_is_black_joker_totem,
                        'is_red_joker' => (bool) $c->campaign_is_red_joker_totem,
                    ]),
                'summoning' => Action::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_advancement_kind', 'summoning')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Action $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'body' => $a->description,
                    ]),
                'crew_card' => CampaignCrewCard::query()
                    ->with(['actions:id,name', 'abilities:id,name'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (CampaignCrewCard $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'body' => $c->description,
                        'actions' => $c->actions->map(fn (Action $a) => ['id' => $a->id, 'name' => $a->name]),
                        'abilities' => $c->abilities->map(fn (Ability $a) => ['id' => $a->id, 'name' => $a->name]),
                    ]),
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
            $hand[] = FateDeck::draw();
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
        ]);

        // CR comes from the per-game snapshot — both crews' CRs at game start
        // are captured on the campaign_games row, so we don't trust the client
        // to send them. Same goes for withdrawal state (pg 20 turn 1-2 path is
        // already redirected to Phase 6 in start(); this is the turn 3+ path).
        $game = $aftermath->campaignGame;
        $crew = $aftermath->crew;
        $isCrewA = $game->crew_a_id === $crew->id;
        $crewCr = $isCrewA ? $game->cr_a : $game->cr_b;
        $opponentCr = $isCrewA ? $game->cr_b : $game->cr_a;

        // Strategic Withdrawal turn 3+ (pg 20): the opposing crew counts as
        // scoring +1 VP higher than the withdrawing crew if the withdrawing
        // crew has ≥ opponent VP. We adjust the withdrawing crew's own VP for
        // its own payday — opponent's payday is unaffected here (each crew
        // runs its own aftermath flow).
        $vp = $data['vp'];
        $withdrew = $game->withdrew_crew_id === $crew->id;
        if ($withdrew && $game->withdrew_turn !== null && $game->withdrew_turn >= 3) {
            $opponentVp = $isCrewA ? $game->vp_b : $game->vp_a;
            $adjusted = CampaignRules::withdrawalAdjustedVp($vp, $opponentVp, $game->withdrew_turn);
            $vp = $adjusted['withdrew_vp'];
        }

        $scrip = CampaignRules::scripFromGame(
            vp: $vp,
            won: $data['won'],
            crewCr: $crewCr,
            opponentCr: $opponentCr,
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
            // Equipment lives on `upgrades` post-consolidation. The `exists`
            // rule requires the row to be campaign-mode equipment specifically.
            'purchases.*' => ['integer', 'exists:upgrades,id'],
        ]);

        $crew = $aftermath->crew;
        $purchaseIds = $data['purchases'] ?? [];

        // Eligibility check + cost tally. Filter to campaign equipment upgrades.
        $items = Upgrade::query()
            ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
            ->where('campaign_upgrade_kind', 'equipment')
            ->whereIn('id', $purchaseIds)
            ->get();
        $totalCc = (int) $items->sum('campaign_cc');
        $ineligible = [];
        foreach ($items as $eq) {
            $eligible = $eq->campaign_is_always_available
                || ($eq->campaign_br !== null && $eq->campaign_br === $data['flip_value']);
            // Red joker enables ttw_only items; otherwise they're filtered out.
            if ($eq->campaign_ttw_only && ! $data['is_red_joker']) {
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
                    // Post-consolidation FK points at upgrades.id. Legacy
                    // equipment_catalog_id stays null on new rows; Phase 5
                    // drops the column entirely.
                    'equipment_upgrade_id' => $eq->id,
                    'source' => $eq->campaign_ttw_only ? 'joker' : 'barter',
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
            // Required when the doctor outcome is AddedInjury (Oops).
            'attempts.*.added_injury_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'attempts.*.added_injury_suit_pool' => ['nullable', 'string', 'in:pc,te'],
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

                // GainedUndead / GainedConstruct: append the characteristic to
                // the model's gained_characteristics JSON column.
                if ($outcome === BackAlleyDoctorOutcomeEnum::GainedUndead || $outcome === BackAlleyDoctorOutcomeEnum::GainedConstruct) {
                    $newCharacteristic = $outcome === BackAlleyDoctorOutcomeEnum::GainedUndead ? 'Undead' : 'Construct';
                    $current = $model->gained_characteristics ?? [];
                    if (! in_array($newCharacteristic, $current, true)) {
                        $current[] = $newCharacteristic;
                        $model->update(['gained_characteristics' => $current]);
                    }
                }

                // AddedInjury (Oops): add a NEW injury using the provided flip.
                if ($outcome === BackAlleyDoctorOutcomeEnum::AddedInjury) {
                    $newFlip = $attempt['added_injury_flip_value'] ?? null;
                    $newPool = $attempt['added_injury_suit_pool'] ?? null;
                    if ($newFlip !== null && $newPool !== null) {
                        $newInjury = Upgrade::query()
                            ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                            ->where('campaign_upgrade_kind', 'injury')
                            ->where('campaign_suit_pool', $newPool)
                            ->where('campaign_flip_value', $newFlip)
                            ->first();
                        $newAttaches = $newInjury && ! str_contains(strtolower($newInjury->name), 'flesh wound');
                        if ($newAttaches && ! $newInjury->campaign_annihilates_model) {
                            $dupCheck = DB::table('campaign_arsenal_model_injuries')
                                ->where('campaign_arsenal_model_id', $model->id)
                                ->where('injury_upgrade_id', $newInjury->id)
                                ->exists();
                            if (! $dupCheck) {
                                DB::table('campaign_arsenal_model_injuries')->insert([
                                    'campaign_arsenal_model_id' => $model->id,
                                    'injury_upgrade_id' => $newInjury->id,
                                    'acquired_aftermath_id' => $locked->id,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                $injCount = DB::table('campaign_arsenal_model_injuries')
                                    ->where('campaign_arsenal_model_id', $model->id)
                                    ->count();
                                if ($injCount >= 3) {
                                    $model->update(['annihilated_at' => now()]);
                                }
                            }
                        } elseif ($newInjury?->campaign_annihilates_model) {
                            $model->update(['annihilated_at' => now()]);
                        }
                    }
                }

                // NoEffect and AddedInjury leave the original injury attached.

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

        // Phase 2 (Payday) is mandatory and cannot be skipped (pg 21).
        if ($aftermath->current_phase < 1 || $aftermath->current_phase >= 6 || $aftermath->current_phase === 2) {
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
            'advancements.*.position_in_xp_track' => ['required', 'integer', 'min:0', 'max:38'],
            'advancements.*.free_choice' => ['nullable', 'array'],
            // Optional: the originating equipment for an attack/tactical
            // modifier that targets an equipment-derived action (pg 31).
            'advancements.*.from_equipment_id' => ['nullable', 'integer', 'exists:campaign_equipment,id'],
            // Optional: required for Totem source_table — server validates
            // the flip-value matches the chosen totem template exactly.
            'advancements.*.flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
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

        // Rule enforcement before we mutate state. Reject the batch if any
        // advancement violates a rule — better than partial application.
        $rejection = $this->validateAdvancementRules($aftermath, $leader, $data['advancements'] ?? []);
        if ($rejection !== null) {
            return redirect()->back()->withMessage($rejection, null, MessageTypeEnum::error);
        }

        $advanced = $this->lockAndAdvance($aftermath, 4, function (CampaignAftermath $locked) use ($leader, $data) {
            // Lazy-init the XP track to the canonical 39-box layout the first
            // time we touch it.
            $track = $leader->xp_track ?? CustomCharacter::defaultXpTrack();
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
            $leader->update(['xp_track' => $track]);

            foreach (($data['advancements'] ?? []) as $a) {
                CampaignLeaderAdvancement::create([
                    'custom_character_id' => $leader->id,
                    'source_aftermath_id' => $locked->id,
                    'source_table' => $a['source_table'],
                    // Post-consolidation FK to core tables (upgrades / actions /
                    // triggers / abilities / custom_characters depending on
                    // source_table). Legacy catalog_id stays null on new rows.
                    'catalog_core_id' => $a['catalog_id'] ?? null,
                    'from_equipment_id' => $a['from_equipment_id'] ?? null,
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
                // Injuries now live on `upgrades` with campaign_upgrade_kind='injury'.
                $injury = Upgrade::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_upgrade_kind', 'injury')
                    ->where('campaign_suit_pool', $f['suit_pool'])
                    ->where('campaign_flip_value', $f['flip_value'])
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

                // Peons never suffer injuries (pg 34).
                if ($model->is_peon) {
                    continue;
                }

                if ($attaches && ! $injury->campaign_annihilates_model) {
                    // No duplicate injuries: if the model already has this
                    // injury, skip the insert rather than stacking it.
                    $alreadyHas = DB::table('campaign_arsenal_model_injuries')
                        ->where('campaign_arsenal_model_id', $model->id)
                        ->where('injury_upgrade_id', $injury->id)
                        ->exists();

                    if (! $alreadyHas) {
                        DB::table('campaign_arsenal_model_injuries')->insert([
                            'campaign_arsenal_model_id' => $model->id,
                            'injury_upgrade_id' => $injury->id,
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

                        // Titled models: cascade injury to all title siblings
                        // sharing the same title_group_key (pg 18).
                        if ($model->title_group_key !== null) {
                            $siblings = CampaignArsenalModel::query()
                                ->where('campaign_crew_id', $model->campaign_crew_id)
                                ->where('title_group_key', $model->title_group_key)
                                ->where('id', '!=', $model->id)
                                ->whereNull('annihilated_at')
                                ->lockForUpdate()
                                ->get();

                            foreach ($siblings as $sibling) {
                                $siblingAlreadyHas = DB::table('campaign_arsenal_model_injuries')
                                    ->where('campaign_arsenal_model_id', $sibling->id)
                                    ->where('injury_upgrade_id', $injury->id)
                                    ->exists();

                                if (! $siblingAlreadyHas) {
                                    DB::table('campaign_arsenal_model_injuries')->insert([
                                        'campaign_arsenal_model_id' => $sibling->id,
                                        'injury_upgrade_id' => $injury->id,
                                        'acquired_aftermath_id' => $locked->id,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);

                                    $siblingCount = DB::table('campaign_arsenal_model_injuries')
                                        ->where('campaign_arsenal_model_id', $sibling->id)
                                        ->count();

                                    if ($siblingCount >= 3) {
                                        $sibling->update(['annihilated_at' => now()]);
                                    }
                                }
                            }
                        }
                    }
                }

                if ($injury->campaign_annihilates_model) {
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

    /**
     * Rule-validation for Phase 4 advancement picks. Returns null if all are
     * valid, or an error-message string on the first violation.
     *
     * Enforces:
     * - Summoning advancement is at most once per CAMPAIGN (pg 54). The check
     *   spans every aftermath the crew has ever submitted — not just this one.
     * - Totem advancement requires an EXACT flip-value match (pg 52). The
     *   client must submit `flip_value`, and the chosen totem template's
     *   `campaign_totem_flip_value` must equal it.
     *
     * @param  array<int, array<string, mixed>>  $advancements
     */
    private function validateAdvancementRules(CampaignAftermath $aftermath, CustomCharacter $leader, array $advancements): ?string
    {
        $sawSummoning = false;
        foreach ($advancements as $a) {
            $source = $a['source_table'] ?? null;

            if ($source === AdvancementTableEnum::Summoning->value) {
                if ($sawSummoning) {
                    return 'Summoning Advancement may only be selected once.';
                }
                $sawSummoning = true;

                // Campaign-wide check: has any prior aftermath in this campaign
                // already produced a summoning advancement on this crew's leader
                // (or totem)? Lookup spans the leader CustomCharacter id since
                // advancements are bound to that.
                $existing = CampaignLeaderAdvancement::query()
                    ->where('custom_character_id', $leader->id)
                    ->where('source_table', AdvancementTableEnum::Summoning)
                    ->exists();
                if ($existing) {
                    return 'Summoning Advancement has already been used in this campaign.';
                }
            }

            if ($source === AdvancementTableEnum::Totem->value) {
                $catalogId = $a['catalog_id'] ?? null;
                $flipValue = $a['flip_value'] ?? null;
                if ($catalogId === null || $flipValue === null) {
                    return 'Totem Advancement requires both a totem choice and the flipped value.';
                }
                $template = CustomCharacter::query()
                    ->where('is_campaign_totem_template', true)
                    ->whereKey($catalogId)
                    ->first();
                if (! $template) {
                    return 'Selected Totem is not a recognized template.';
                }
                if ((int) $template->campaign_totem_flip_value !== (int) $flipValue) {
                    return 'Totem Advancement requires an exact flip-value match — chosen totem does not match the flip.';
                }
            }
        }

        return null;
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

        return [
            'leader_id' => $leader->id,
            'leader_name' => $leader->name,
            'tag' => $leader->tag,
            'track' => $leader->xp_track ?? CustomCharacter::defaultXpTrack(),
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
