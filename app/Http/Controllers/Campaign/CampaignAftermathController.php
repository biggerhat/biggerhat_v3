<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\LuckyMiss;
use App\Models\CustomCharacter;
use App\Models\Upgrade;
use App\Services\Campaign\LeaderAdvancementService;
use App\Services\CampaignRules;
use App\Support\Campaign\AftermathCatalog;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Six-phase Aftermath wizard per rulebook pg 20–36:
 *
 *   1. Draw Aftermath Hand   — server snapshots a hand from the fate deck
 *   2. Payday                — auto-computes scrip from VP + win bonus + CR diff
 *   3. Barter                — equipment purchases (BR + suit-pool eligibility)
 *   4. Advance Leader        — server-computed XP + advancement picks (tier-gated)
 *   5. Back-Alley Doctor     — pay scrip per attempt; resolve doctor table + jokers
 *   6. Determine Injuries    — injury flips per killed model, incl. jokers
 *
 * Each phase has its own handler that mutates arsenal state (injuries, equipment,
 * advancements, Lucky Miss / Traitor / Doppelganger). The `advance()` endpoint is
 * the explicit "nothing to record this phase" skip for the optional phases.
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
            // True only when the kill list comes from a linked tracker run, so
            // the UI can present it as authoritative. Solo / manually-logged
            // games have no base game — the list is then the full roster and
            // the player must pick who actually died (pg 34).
            'kills_are_authoritative' => $aftermath->campaignGame->base_game_id !== null,
            // Phase-gated lazy props. Inertia evaluates closures on full visits,
            // so gating on `current_phase` keeps each phase's catalog query off
            // the others. Read-side queries live in AftermathCatalog.
            'equipment_catalog' => fn () => $aftermath->current_phase === 3 ? AftermathCatalog::equipment() : null,
            'crew_injuries' => fn () => $aftermath->current_phase === 5 ? AftermathCatalog::crewInjuries($aftermath->campaign_crew_id) : null,
            'doctor_results' => fn () => $aftermath->current_phase === 5 ? AftermathCatalog::doctorResults() : null,
            // Phase 5 needs it too for the doctor "Oops" added-injury pick.
            'injury_catalog' => fn () => in_array($aftermath->current_phase, [5, 6], true) ? AftermathCatalog::injuries() : null,
            // Destinations for a Traitor (black joker) defector — the campaign's
            // other crews. Solo-logged games have no opponent on the game row,
            // so the player picks here. Only needed during Determine Injuries.
            'traitor_target_crews' => fn () => $aftermath->current_phase === 6
                ? CampaignCrew::query()
                    ->where('campaign_id', $aftermath->campaignGame->campaign_id)
                    ->where('id', '!=', $aftermath->campaign_crew_id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : null,
            'xp_track' => fn () => $aftermath->current_phase === 4 ? $this->loadXpTrackForCrew($aftermath) : null,
            'advancement_catalogs' => fn () => $aftermath->current_phase === 4 ? AftermathCatalog::advancementCatalogs() : null,
            // Pre-fill the draw-hand (schemes / withdrawal) and payday (VP / win /
            // CR) forms from the logged game so the player confirms, not re-enters.
            'prefill' => $this->aftermathPrefill($aftermath),
        ]);
    }

    /**
     * The scoring the player already supplied when logging the game, mapped to
     * this crew's perspective, so the Aftermath's draw-hand + payday steps come
     * pre-filled. Tracked games that haven't scored yet just yield zeros.
     *
     * @return array<string, int|bool>
     */
    private function aftermathPrefill(CampaignAftermath $aftermath): array
    {
        $game = $aftermath->campaignGame;
        $isCrewA = $game->crew_a_id === $aftermath->campaign_crew_id;

        return [
            'vp_self' => (int) ($isCrewA ? $game->vp_a : $game->vp_b),
            'vp_opponent' => (int) ($isCrewA ? $game->vp_b : $game->vp_a),
            'schemes_completed' => (int) ($isCrewA ? $game->schemes_completed_a : $game->schemes_completed_b),
            'won' => $game->winner_crew_id === $aftermath->campaign_crew_id,
            'withdrew' => $game->withdrew_crew_id === $aftermath->campaign_crew_id,
            'crew_cr' => (int) ($isCrewA ? $game->cr_a : $game->cr_b),
            'opponent_cr' => (int) ($isCrewA ? $game->cr_b : $game->cr_a),
        ];
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

        // The player draws their own aftermath hand from their physical fate
        // deck (pg 20) — we record only the entitled size so the wizard can tell
        // them how many to draw, rather than dealing cards for them.
        $advanced = $this->lockAndAdvance($aftermath, 1, function (CampaignAftermath $locked) use ($size) {
            if ($locked->hand_drawn) {
                return;
            }
            $locked->update([
                'hand_drawn' => ['size' => $size],
                'current_phase' => 2,
            ]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage(sprintf('Draw %d card(s) from your fate deck. On to Payday.', $size));
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
        // scoring +1 VP higher than the withdrawing crew when the withdrawing
        // crew has ≥ opponent VP. Each crew runs its own aftermath, so we resolve
        // BOTH sides here off this crew's claimed VP + the other crew's recorded
        // game VP — whether this crew withdrew or its opponent did.
        $vp = $data['vp'];
        $otherCrewVp = $isCrewA ? $game->vp_b : $game->vp_a;
        $withdrewTurn = $game->withdrew_turn;

        if ($withdrewTurn !== null && $withdrewTurn >= 3 && $game->withdrew_crew_id !== null) {
            if ($game->withdrew_crew_id === $crew->id) {
                // This crew withdrew — cap its own scoring credit.
                $vp = CampaignRules::withdrawalAdjustedVp($vp, $otherCrewVp, $withdrewTurn)['withdrew_vp'];
            } else {
                // The opponent withdrew — this crew counts as scoring +1 over the
                // withdrawer when the withdrawer had ≥ this crew's VP.
                $vp = CampaignRules::withdrawalAdjustedVp($otherCrewVp, $vp, $withdrewTurn)['opponent_vp'];
            }
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
            'purchases' => ['nullable', 'array'],
            // Equipment lives on `upgrades` post-consolidation. The `exists`
            // rule requires the row to be campaign-mode equipment specifically.
            'purchases.*' => ['integer', 'exists:upgrades,id'],
        ]);

        $crew = $aftermath->crew;
        $purchaseIds = $data['purchases'] ?? [];

        // The barter flip + BR/suit eligibility is resolved at the table by the
        // player (pg 21-30); the app just records what they bought. Look up the
        // chosen equipment by id, tally cc, and charge scrip.
        $items = Upgrade::query()
            ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
            ->where('campaign_upgrade_kind', 'equipment')
            ->whereIn('id', $purchaseIds)
            ->get();
        $totalCc = (int) $items->sum('campaign_cc');

        if ($totalCc > $crew->scrip) {
            return redirect()->back()->withMessage(
                "Not enough scrip — needs {$totalCc}, have {$crew->scrip}.",
                null,
                MessageTypeEnum::error,
            );
        }

        $advanced = $this->lockAndAdvance($aftermath, 3, function (CampaignAftermath $locked) use ($crew, $items, $totalCc) {
            foreach ($items as $eq) {
                CampaignEquipment::create([
                    'campaign_crew_id' => $crew->id,
                    // Post-consolidation FK points at upgrades.id.
                    'equipment_upgrade_id' => $eq->id,
                    'source' => $eq->campaign_ttw_only ? 'joker' : 'barter',
                    'acquired_aftermath_id' => $locked->id,
                ]);
            }

            if ($totalCc > 0) {
                $crew->decrement('scrip', $totalCc);
            }

            $locked->update(['current_phase' => 4]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage($items->isEmpty()
                ? 'Barter skipped. On to Advance Leader.'
                : 'Bartered '.$items->count().' item(s) for '.$totalCc.' scrip. On to Advance Leader.');
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
            // The player makes the doctor flip at the table and picks the result
            // they got from the chart (pg 33); the server applies its outcome.
            'attempts.*.result_id' => ['required', 'integer', 'exists:back_alley_doctor_results,id'],
            'attempts.*.cheated' => ['nullable', 'boolean'],
            // Required when the chosen result adds a new injury (Oops / flip 9
            // RemovedAndReflip) — the injury picked from the chart.
            'attempts.*.added_injury_upgrade_id' => ['nullable', 'integer', 'exists:upgrades,id'],
            // The Lucky Miss result rolled after a red-joker annihilation; or, if
            // that Lucky Miss flip is itself a joker, Doppelganger.
            'attempts.*.lucky_miss_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'attempts.*.lucky_miss_is_joker' => ['nullable', 'boolean'],
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
                $model = CampaignArsenalModel::query()->whereKey($pivot->campaign_arsenal_model_id)->lockForUpdate()->first();
                if (! $model || $model->campaign_crew_id !== $crew->id) {
                    continue;
                }

                // The player picked the result row they flipped at the table.
                $result = BackAlleyDoctorResult::query()->whereKey($attempt['result_id'])->first();

                $outcome = $result ? $result->outcome_kind : BackAlleyDoctorOutcomeEnum::NoEffect;

                // Outcomes that annihilate the targeted injury. RemovedAndReflip
                // (flip 9) removes it AND then reflips for a fresh one below.
                $removesInjury = in_array($outcome, [
                    BackAlleyDoctorOutcomeEnum::Removed,
                    BackAlleyDoctorOutcomeEnum::RemovedAndReflip,
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

                // AddedInjury ("Oops", original stays) and RemovedAndReflip (flip
                // 9, original already removed above) both attach a NEW injury from
                // the client-supplied reflip. attachInjury() enforces flesh-wound /
                // duplicate / 3-injury annihilation / titled-cascade rules.
                if ($outcome === BackAlleyDoctorOutcomeEnum::AddedInjury || $outcome === BackAlleyDoctorOutcomeEnum::RemovedAndReflip) {
                    $newInjuryId = $attempt['added_injury_upgrade_id'] ?? null;
                    if ($newInjuryId !== null) {
                        $newInjury = Upgrade::query()
                            ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                            ->where('campaign_upgrade_kind', 'injury')
                            ->whereKey($newInjuryId)
                            ->first();
                        if ($newInjury) {
                            $model->attachInjury($newInjury, $locked->id);
                        }
                    }
                }

                // LuckyMissReflip (red joker, pg 33): the injury was annihilated
                // above; the model then flips on the Lucky Miss table and keeps
                // the result. An any-joker Lucky Miss flip is Doppelganger — a
                // free copy joins this crew's arsenal (pg 36).
                if ($outcome === BackAlleyDoctorOutcomeEnum::LuckyMissReflip) {
                    if (! empty($attempt['lucky_miss_is_joker'])) {
                        $model->copyForCampaign($model->campaign_crew_id, 'doppelganger', ignoredForLimits: true);
                    } else {
                        $luckyMiss = LuckyMiss::query()
                            ->where('flip_value', $attempt['lucky_miss_flip_value'] ?? null)
                            ->where('is_doppelganger', false)
                            ->first();
                        if ($luckyMiss) {
                            $model->applyLuckyMiss($luckyMiss->id);
                        }
                    }
                }

                // NoEffect and AddedInjury leave the original injury attached.

                DB::table('campaign_aftermath_doctor')->insert([
                    'campaign_aftermath_id' => $locked->id,
                    'target_arsenal_model_id' => $model->id,
                    // A "removed" outcome already deleted the injury pivot above,
                    // so the audit row keeps a null reference (FK is nullOnDelete)
                    // rather than a dangling id that fails the FK in MySQL.
                    'target_injury_id' => $removesInjury ? null : $pivot->id,
                    // No raw flip value any more — the player records the chosen
                    // result row, not a flip number (column is nullable).
                    'flip_value' => null,
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
            // In-game facts the system can't derive on its own (the tracker
            // doesn't capture "Interacted within 6\" of the enemy DZ", and a
            // solo loss is indistinguishable from a draw). The conditional
            // bonuses are gated server-side by the Leader's actual tag and the
            // total is computed by CampaignRules — the client never sends a raw
            // XP total, so it can't inflate it or claim the wrong tag's bonus.
            'bruiser_killed_non_peon' => ['required', 'boolean'],
            'strategist_interacted' => ['required', 'boolean'],
            'lost' => ['required', 'boolean'],
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
            'advancements.*.totem_name' => ['nullable', 'string', 'max:100'],
            'advancements.*.totem_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'advancements.*.totem_base' => ['nullable', 'string', 'max:10'],
        ]);

        $aftermath->loadMissing('crew');
        $leader = $aftermath->crew->leader;

        if (! $leader) {
            return redirect()->back()->withMessage(
                'No active leader for this crew — build one first.',
                null,
                MessageTypeEnum::error,
            );
        }

        // Compute XP from the canonical rule (pg 31), gating the conditional
        // bonuses on the Leader's actual tag. A leader with no tag earns only
        // the play (+1) and lost (+1) points.
        $tag = LeaderTagEnum::tryFrom((string) ($leader->tag ?? ''));
        $xpEarned = $tag === null
            ? min(3, 1 + ($data['lost'] ? 1 : 0))
            : CampaignRules::xpFromGame(
                tag: $tag,
                bruiserKilledNonPeon: $data['bruiser_killed_non_peon'],
                strategistInteractedInEnemyDz: $data['strategist_interacted'],
                lost: $data['lost'],
            );

        // Rule enforcement before we mutate state. Reject the batch if any
        // advancement violates a rule — better than partial application.
        $advancementService = app(LeaderAdvancementService::class);
        $rejection = $advancementService->validate($leader, $data['advancements'] ?? []);
        if ($rejection !== null) {
            return redirect()->back()->withMessage($rejection, null, MessageTypeEnum::error);
        }

        $advanced = $this->lockAndAdvance($aftermath, 4, function (CampaignAftermath $locked) use ($leader, $data, $xpEarned, $advancementService) {
            // Lazy-init the XP track to the canonical 39-box layout the first
            // time we touch it.
            $track = $leader->xp_track ?? CustomCharacter::defaultXpTrack();
            $toFill = $xpEarned;
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

            $advancementService->create($leader, $data['advancements'] ?? [], $locked->id);

            $locked->update(['current_phase' => 5]);
        });

        if (! $advanced) {
            return redirect()->route('campaigns.aftermaths.show', $aftermath);
        }

        return redirect()->route('campaigns.aftermaths.show', $aftermath)
            ->withMessage("Advanced Leader: {$xpEarned} XP, ".count($data['advancements'] ?? []).' advancement(s).');
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
            // Exactly one of arsenal_model_id / custom_character_id must be set.
            // arsenal_model_id → standard crew model; custom_character_id → leader/totem.
            'flips.*.arsenal_model_id' => ['nullable', 'integer', 'exists:campaign_arsenal_models,id'],
            'flips.*.custom_character_id' => ['nullable', 'integer', 'exists:custom_characters,id'],
            // A red-joker kill is a "Close Call" → the model flips on the Lucky
            // Miss table instead of taking an injury (pg 35-36). A black joker is
            // "Traitor" → the model defects (pg 34). flip_value / suit_pool are
            // only required for a normal injury flip.
            'flips.*.is_red_joker' => ['nullable', 'boolean'],
            'flips.*.is_black_joker' => ['nullable', 'boolean'],
            // Set when the Lucky Miss flip itself is a joker → Doppelganger.
            'flips.*.lucky_miss_is_joker' => ['nullable', 'boolean'],
            // The injury is now chosen directly (the player resolves the flip at
            // the table and picks the matching injury); absent for joker flips.
            'flips.*.injury_upgrade_id' => ['nullable', 'integer', 'exists:upgrades,id'],
            'flips.*.lucky_miss_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            // The crew a Traitor (black joker) defector joins. Defaults to the
            // game's opponent, but solo-logged games have no opponent crew
            // (crew_b_id is null), so the player picks a destination crew from
            // the campaign. Must be another crew in the same campaign.
            'flips.*.traitor_target_crew_id' => ['nullable', 'integer', Rule::exists('campaign_crews', 'id')
                ->where('campaign_id', $aftermath->campaignGame->campaign_id)],
        ]);

        $flips = $data['flips'] ?? [];

        // Opponent crew (if any) receives Traitor defectors by default.
        $game = $aftermath->campaignGame;
        $opponentCrewId = $game->crew_a_id === $aftermath->campaign_crew_id ? $game->crew_b_id : $game->crew_a_id;

        $this->lockAndAdvance($aftermath, 6, function (CampaignAftermath $locked) use ($flips, $opponentCrewId) {
            foreach ($flips as $f) {
                $customCharId = ! empty($f['custom_character_id']) ? (int) $f['custom_character_id'] : null;
                $arsenalModelId = ! empty($f['arsenal_model_id']) ? (int) $f['arsenal_model_id'] : null;

                if ($customCharId !== null) {
                    // ── Leader / Totem path ──────────────────────────────────
                    $char = CustomCharacter::query()
                        ->whereKey($customCharId)
                        ->where('campaign_crew_id', $locked->campaign_crew_id)
                        ->lockForUpdate()
                        ->first();
                    if (! $char || $char->annihilated_at !== null) {
                        continue;
                    }

                    // Rulebook: black joker (Traitor) for a leader/totem → reflip
                    // the result. We skip the result entirely (no action taken).
                    if (! empty($f['is_black_joker'])) {
                        continue;
                    }

                    // Red joker (Close Call): Lucky Miss. Leader/totem lucky-miss
                    // effects are not yet tracked — treat as no injury taken.
                    if (! empty($f['is_red_joker'])) {
                        continue;
                    }

                    if (empty($f['injury_upgrade_id'])) {
                        continue;
                    }
                    $injury = Upgrade::query()
                        ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                        ->where('campaign_upgrade_kind', 'injury')
                        ->whereKey($f['injury_upgrade_id'])
                        ->first();
                    if (! $injury) {
                        continue;
                    }

                    $this->attachInjuryToCustomChar($char, $injury, $locked->id);

                    continue;
                }

                if ($arsenalModelId === null) {
                    continue;
                }

                // ── Standard arsenal model path ──────────────────────────────
                // Pessimistic lock so the dup-check + count + annihilation in
                // attachInjury() is atomic against concurrent submissions.
                $model = CampaignArsenalModel::query()
                    ->whereKey($arsenalModelId)
                    ->lockForUpdate()
                    ->first();
                if (! $model || $model->campaign_crew_id !== $locked->campaign_crew_id) {
                    continue;
                }

                // Black joker (Traitor): the model defects. It's annihilated from
                // this crew and, if an opponent crew is recorded, joins theirs
                // with its injuries (pg 34). Solo / no-opponent → just annihilate.
                if (! empty($f['is_black_joker'])) {
                    // Player-chosen destination wins, else the game's opponent.
                    // Never let a model defect back to its own crew.
                    $targetCrewId = $f['traitor_target_crew_id'] ?? $opponentCrewId;
                    if ($targetCrewId !== null && (int) $targetCrewId !== $model->campaign_crew_id) {
                        $model->copyForCampaign((int) $targetCrewId, 'traitor');
                    }
                    $model->update(['annihilated_at' => now()]);

                    continue;
                }

                // Red joker (Close Call): flip on the Lucky Miss table instead of
                // taking an injury. An any-joker Lucky Miss result is Doppelganger:
                // a free copy of the model joins this crew's arsenal, ignored for
                // model limits (pg 36).
                if (! empty($f['is_red_joker'])) {
                    if (! empty($f['lucky_miss_is_joker'])) {
                        $model->copyForCampaign($model->campaign_crew_id, 'doppelganger', ignoredForLimits: true);

                        continue;
                    }

                    $luckyMiss = LuckyMiss::query()
                        ->where('flip_value', $f['lucky_miss_flip_value'] ?? null)
                        ->where('is_doppelganger', false)
                        ->first();
                    if ($luckyMiss) {
                        $model->applyLuckyMiss($luckyMiss->id);
                    }

                    continue;
                }

                // The injury is chosen directly from the catalog now (the player
                // resolves the flip at the table). No pick → nothing to attach.
                if (empty($f['injury_upgrade_id'])) {
                    continue;
                }
                $injury = Upgrade::query()
                    ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                    ->where('campaign_upgrade_kind', 'injury')
                    ->whereKey($f['injury_upgrade_id'])
                    ->first();

                if (! $injury) {
                    continue;
                }

                // Peon / flesh-wound / duplicate / annihilation / titled-cascade
                // rules all live in the model helper now.
                $model->attachInjury($injury, $locked->id);
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

    private function killedNonPeonModelsForCrew(CampaignAftermath $aftermath): \Illuminate\Support\Collection
    {
        // Auto-detect via GameCrewMember death events from the wrapping game.
        // Falls back to "all active non-peon arsenal models + current leader/totem"
        // when there's no linked base game — useful for testing in isolation.
        $baseGameId = $aftermath->campaignGame->base_game_id ?? null;

        if (! $baseGameId) {
            $arsenalRows = CampaignArsenalModel::query()
                ->where('campaign_crew_id', $aftermath->campaign_crew_id)
                ->active()
                ->where('is_peon', false)
                ->with('character:id,display_name,station,faction')
                ->get(['id', 'campaign_crew_id', 'character_id', 'label'])
                ->map(fn ($m) => $this->arsenalModelRow($m))
                ->toBase();

            return $arsenalRows->merge($this->customCharacterKillRows($aftermath->campaign_crew_id, null))->values();
        }

        $killedCharacterIds = DB::table('game_crew_members')
            ->where('game_id', $baseGameId)
            ->where('is_killed', true)
            ->whereNotNull('character_id')
            ->pluck('character_id')
            ->all();

        $killedCustomCharIds = DB::table('game_crew_members')
            ->where('game_id', $baseGameId)
            ->where('is_killed', true)
            ->whereNotNull('custom_character_id')
            ->pluck('custom_character_id')
            ->all();

        $arsenalRows = collect();
        if (! empty($killedCharacterIds)) {
            $arsenalRows = CampaignArsenalModel::query()
                ->where('campaign_crew_id', $aftermath->campaign_crew_id)
                ->active()
                ->where('is_peon', false)
                ->whereIn('character_id', $killedCharacterIds)
                ->with('character:id,display_name,station,faction')
                ->get(['id', 'campaign_crew_id', 'character_id', 'label'])
                ->map(fn ($m) => $this->arsenalModelRow($m))
                ->toBase();
        }

        $customCharRows = ! empty($killedCustomCharIds)
            ? $this->customCharacterKillRows($aftermath->campaign_crew_id, $killedCustomCharIds)
            : collect();

        return $arsenalRows->merge($customCharRows)->values();
    }

    /**
     * Attach an injury upgrade to a leader or totem CustomCharacter.
     * Mirrors CampaignArsenalModel::attachInjury() but stores via custom_character_id.
     * Three distinct injuries → annihilate (set annihilated_at).
     */
    private function attachInjuryToCustomChar(CustomCharacter $char, Upgrade $injury, int $aftermathId): void
    {
        // Self-annihilating (Killed Off): just mark annihilated, no row stored.
        if ($injury->campaign_annihilates_model) {
            $char->update(['annihilated_at' => now()]);

            return;
        }

        // Purely cosmetic "Just a Flesh Wound" results attach nothing.
        if (str_contains(strtolower((string) $injury->name), 'flesh wound')) {
            return;
        }

        $existingCount = DB::table('campaign_arsenal_model_injuries')
            ->where('custom_character_id', $char->id)
            ->count();

        // No duplicates: ignore if this injury is already attached.
        $alreadyHas = DB::table('campaign_arsenal_model_injuries')
            ->where('custom_character_id', $char->id)
            ->where('injury_upgrade_id', $injury->id)
            ->exists();
        if ($alreadyHas) {
            return;
        }

        DB::table('campaign_arsenal_model_injuries')->insert([
            'campaign_arsenal_model_id' => null,
            'custom_character_id' => $char->id,
            'injury_upgrade_id' => $injury->id,
            'acquired_aftermath_id' => $aftermathId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($existingCount + 1 >= 3) {
            $char->update(['annihilated_at' => now()]);
        }
    }

    /** Shape a CampaignArsenalModel as the unified killed-model payload. */
    private function arsenalModelRow(CampaignArsenalModel $m): array
    {
        return [
            'id' => $m->id,
            'campaign_crew_id' => $m->campaign_crew_id,
            'character_id' => $m->character_id,
            'custom_character_id' => null,
            'label' => $m->label,
            'display_name' => $m->character ? $m->character->display_name : '',
            'character' => $m->character ? [
                'id' => $m->character->id,
                'display_name' => $m->character->display_name,
                'station' => $m->character->getRawOriginal('station') ?? '',
            ] : null,
        ];
    }

    /**
     * Fetch current leader/totem custom characters for this crew that appear in
     * the killed list. When $killedIds is null, return all (fallback with no game).
     *
     * @param  int[]|null  $killedIds
     */
    private function customCharacterKillRows(int $crewId, ?array $killedIds): \Illuminate\Support\Collection
    {
        $query = CustomCharacter::query()
            ->where('campaign_crew_id', $crewId)
            ->where('current', true)
            ->whereNull('annihilated_at')
            ->where(fn ($q) => $q->where('is_campaign_leader', true)->orWhere('is_campaign_totem', true));

        if ($killedIds !== null) {
            $query->whereIn('id', $killedIds);
        }

        return $query->get(['id', 'name', 'station', 'is_campaign_leader', 'is_campaign_totem'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'campaign_crew_id' => $crewId,
                'character_id' => null,
                'custom_character_id' => $c->id,
                'label' => null,
                'display_name' => $c->name.($c->is_campaign_leader ? ' (Leader)' : ' (Totem)'),
                'character' => null,
            ]);
    }

    private function loadXpTrackForCrew(CampaignAftermath $aftermath): ?array
    {
        $aftermath->loadMissing('crew');
        $leader = $aftermath->crew->leader;

        if (! $leader) {
            return null;
        }

        return [
            'leader_id' => $leader->id,
            'leader_name' => $leader->name,
            'tag' => $leader->tag,
            'track' => $leader->xp_track ?? CustomCharacter::defaultXpTrack(),
            'leader_actions' => collect($leader->actions ?? [])->values()->map(fn ($a, $i) => [
                'index' => $i,
                'name' => $a['name'] ?? '',
                'category' => $a['category'] ?? $a['type'] ?? '',
            ])->all(),
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
