<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Enums\CharacterStationEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Trigger;
use App\Support\Campaign\AftermathCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared Leadership-Experience advancement engine (pg 31, 38-50). Both the
 * Aftermath's Advance-Leader step and the Arsenal Sheet's per-box logging use
 * this so the tier-gating / summoning-once / totem / flip-ceiling rules and the
 * record shape stay in one place.
 *
 * Attack Mod / Tactical Mod / Action / Ability advancements are picked from
 * their own dedicated tables (AdvancementAttackMod / AdvancementTacticalMod /
 * AdvancementAction / AdvancementAbility). Each row either points at a real,
 * already-existing Trigger/Action/Ability via a nullable Lookup FK (reuse its
 * stat block verbatim, same as before this table split) or is a bespoke
 * campaign-only entry with its own name/effect text (and, for actions, its
 * own stat_block) applied directly.
 */
class LeaderAdvancementService
{
    /**
     * Returns an error string if any advancement breaks a rule, else null.
     *
     * @param  array<int, array<string, mixed>>  $advancements
     */
    public function validate(CustomCharacter $leader, array $advancements): ?string
    {
        // XP-track box tiers keyed by index — the box reached caps tier (pg 31).
        $track = $leader->xp_track ?? CustomCharacter::defaultXpTrack();
        $boxTierByIndex = [];
        foreach ($track as $box) {
            $boxTierByIndex[$box['index']] = $box['tier'] ?? null;
        }

        $crewHasTotem = CustomCharacter::query()
            ->where('campaign_crew_id', $leader->campaign_crew_id)
            ->where('is_campaign_totem', true)
            ->where('current', true)
            ->exists();

        $sawSummoning = false;
        foreach ($advancements as $a) {
            $table = AdvancementTableEnum::tryFrom((string) ($a['source_table'] ?? ''));
            if (! $table) {
                return 'Unknown advancement table.';
            }

            // Tier gate against the XP box being filled.
            $position = $a['position_in_xp_track'] ?? null;
            $boxTier = $position !== null ? ($boxTierByIndex[$position] ?? null) : null;
            if ($boxTier === null) {
                return 'That experience box does not grant an advancement.';
            }
            if ($table->tier() > $boxTier) {
                return "A tier {$table->tier()} advancement cannot be taken at a tier {$boxTier} experience box.";
            }

            if ($table === AdvancementTableEnum::Summoning) {
                if ($sawSummoning) {
                    return 'Summoning Advancement may only be selected once.';
                }
                $sawSummoning = true;

                // Campaign-wide check across every prior aftermath on this leader.
                $existing = CampaignLeaderAdvancement::query()
                    ->where('custom_character_id', $leader->id)
                    ->where('source_table', AdvancementTableEnum::Summoning)
                    ->exists();
                if ($existing) {
                    return 'Summoning Advancement has already been used in this campaign.';
                }
            }

            if ($table === AdvancementTableEnum::Totem) {
                if ($crewHasTotem) {
                    return 'Totem Advancement is only available while the crew has no totem.';
                }

                $catalogId = $a['catalog_id'] ?? null;
                if ($catalogId === null) {
                    return 'Totem Advancement requires a totem choice.';
                }
                $template = CustomCharacter::query()
                    ->where('is_campaign_totem_template', true)
                    ->whereKey($catalogId)
                    ->first();
                if (! $template) {
                    return 'Selected Totem is not a recognized template.';
                }
            }

            $catalogId = $a['catalog_id'] ?? null;

            // Attack Mod / Tactical Mod share one fetched row for the flip-ceiling,
            // Joker-gate, and Skl Boost range checks below — one query covers all three.
            $attackTacticalRow = null;
            if ($catalogId !== null && ($table === AdvancementTableEnum::AttackMod || $table === AdvancementTableEnum::TacticalMod)) {
                $attackTacticalRow = match ($table) {
                    AdvancementTableEnum::AttackMod => AdvancementAttackMod::query()->whereKey($catalogId)
                        ->first(['flip_value', 'is_black_joker', 'is_red_joker', 'modifier_type', 'skl_from', 'skl_from_max']),
                    AdvancementTableEnum::TacticalMod => AdvancementTacticalMod::query()->whereKey($catalogId)
                        ->first(['flip_value', 'is_black_joker', 'is_red_joker', 'modifier_type', 'skl_from', 'skl_from_max']),
                    default => null,
                };
            }

            // Flip-and-choose ceiling. Each table's catalog row carries its own
            // flip_value; the chosen option must be <= the flipped card.
            $flip = $a['flip_value'] ?? null;
            if ($catalogId !== null && $flip !== null) {
                $rowFlip = ($attackTacticalRow ? $attackTacticalRow->flip_value : null) ?? match ($table) {
                    AdvancementTableEnum::Action => AdvancementAction::query()->whereKey($catalogId)->value('flip_value'),
                    AdvancementTableEnum::Ability => AdvancementAbility::query()->whereKey($catalogId)->value('flip_value'),
                    default => null,
                };
                if ($rowFlip !== null && (int) $rowFlip > (int) $flip) {
                    return "That advancement needs a flip of {$rowFlip} or higher — your flip was {$flip}.";
                }
            }

            // Joker gate (Attack Mod / Tactical Mod, pg 38-43): a row with either
            // Joker flag set requires the player to declare which card they
            // flipped. Attack Mod's two rows have BOTH flags set ("Any Joker" —
            // either color qualifies); Tactical Mod's two rows each have exactly
            // one flag set (Red and Black grant different named triggers) — the
            // same declared-color check covers both shapes.
            if ($attackTacticalRow && ($attackTacticalRow->is_black_joker || $attackTacticalRow->is_red_joker)) {
                $jokerColor = $a['joker_color'] ?? null;
                $satisfied = ($jokerColor === 'red' && $attackTacticalRow->is_red_joker) || ($jokerColor === 'black' && $attackTacticalRow->is_black_joker);
                if (! $satisfied) {
                    return 'That advancement requires flipping a Joker — declare which one you flipped.';
                }
            }

            // Target resolution (pg 31, 38-43): an Attack/Tactical Mod applies to
            // the Leader (default), the crew's current Totem, or an action
            // granted by owned Equipment — ownership is verified here so a
            // client can't target another crew's totem/equipment or an action
            // that equipment doesn't actually grant. Also resolves the target
            // action's current Skl for the Skl Boost range check below.
            if ($attackTacticalRow) {
                $targetError = $this->validateAttackTacticalTarget($leader, $a, $attackTacticalRow);
                if (is_string($targetError)) {
                    return $targetError;
                }
            }

            // Ability/Summoning target resolution (pg 44-51): applies to the
            // Leader (default) or the crew's current Totem — same routing as
            // Attack/Tactical Mod, minus the per-action Skl range check (an
            // Ability/Summoning action isn't attached to a specific existing
            // action).
            if ($table === AdvancementTableEnum::Ability || $table === AdvancementTableEnum::Summoning) {
                $appliedToCustomCharacterId = $a['applied_to_custom_character_id'] ?? null;
                if ($appliedToCustomCharacterId !== null) {
                    $totemExists = CustomCharacter::query()
                        ->whereKey($appliedToCustomCharacterId)
                        ->where('campaign_crew_id', $leader->campaign_crew_id)
                        ->where('is_campaign_totem', true)
                        ->where('current', true)
                        ->exists();
                    if (! $totemExists) {
                        return "Selected Totem doesn't belong to this crew.";
                    }
                }
            }

            // Any Joker (Action/Ability, pg 49/51): "choose any action/ability on
            // a non-totem, non-master model that shares a keyword with your
            // leader with a cost of 10 or less." Verified authoritatively
            // against the submitted source, not the client-reported name.
            if ($catalogId !== null && ($table === AdvancementTableEnum::Action || $table === AdvancementTableEnum::Ability)) {
                $isJoker = match ($table) {
                    AdvancementTableEnum::Action => (bool) AdvancementAction::query()->whereKey($catalogId)->value('is_joker'),
                    AdvancementTableEnum::Ability => (bool) AdvancementAbility::query()->whereKey($catalogId)->value('is_joker'),
                    default => false,
                };
                if ($isJoker) {
                    $sourceId = $a['free_choice']['source_id'] ?? null;
                    $sourceCharacterId = $a['free_choice']['source_character_id'] ?? null;
                    if ($sourceId === null || $sourceCharacterId === null) {
                        return 'Any Joker requires picking a free action/ability from an eligible ally.';
                    }

                    $keywordIds = array_column($leader->keywords ?? [], 'id');
                    $relation = $table === AdvancementTableEnum::Action ? 'actions' : 'abilities';
                    $valid = ! empty($keywordIds) && Character::query()
                        ->whereKey($sourceCharacterId)
                        ->where('cost', '<=', 10)
                        ->where(fn ($w) => $w->whereNull('station')->orWhere('station', '!=', CharacterStationEnum::Master->value))
                        ->whereDoesntHave('keywords', fn ($k) => $k->where('name', 'like', '%Totem%'))
                        ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
                        ->whereHas($relation, fn ($r) => $r->whereKey($sourceId))
                        ->exists();
                    if (! $valid) {
                        return 'Any Joker pick must be a non-master, non-totem ally (cost <= 10) that shares a keyword with your leader.';
                    }
                }
            }

            // Tier-4 Crew Card (pg 32, 54): "an effect granted by any crew
            // card associated with a master that has a keyword this crew
            // chose." When the catalog row itself is tied to a master
            // (master_id), that's authoritative — the client can't submit a
            // mismatched "borrowed from" master for someone else's card.
            // Only a generic (master_id null) row still needs the client to
            // name its source master.
            if ($table === AdvancementTableEnum::CrewCard && $catalogId !== null) {
                $crewCardForSource = CampaignCrewCard::find($catalogId);
                $sourceMasterId = $crewCardForSource->master_id ?? ($a['free_choice']['source_character_id'] ?? null);
                if ($sourceMasterId === null) {
                    return 'Crew Card Advancement requires naming the master this effect is borrowed from.';
                }

                $keywordIds = array_column($leader->keywords ?? [], 'id');
                $validMaster = ! empty($keywordIds) && Character::query()
                    ->whereKey($sourceMasterId)
                    ->where('station', CharacterStationEnum::Master->value)
                    ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
                    ->exists();
                if (! $validMaster) {
                    return 'Crew Card Advancement must name a master that shares a keyword with your leader.';
                }

                $currentStarterEffectId = CampaignCrew::query()->whereKey($leader->campaign_crew_id)->value('crew_card_effect_id');
                $alreadyHeld = (int) $currentStarterEffectId === (int) $catalogId
                    || CampaignCrewCardAdvancement::query()
                        ->where('campaign_crew_id', $leader->campaign_crew_id)
                        ->where('crew_card_effect_id', $catalogId)
                        ->exists();
                if ($alreadyHeld) {
                    return 'This crew already holds that Crew Card effect.';
                }

                // A borrowed effect that itself requires a token/marker/upgrade
                // choice (pg 17-18) must pick from the same constrained pool
                // Starting Arsenal uses, scoped to this crew's keywords.
                $crewCard = CampaignCrewCard::find($catalogId);
                $requiredChoiceType = match (true) {
                    (bool) $crewCard?->requires_token_choice => 'token',
                    (bool) $crewCard?->requires_marker_choice => 'marker',
                    (bool) $crewCard?->requires_upgrade_type_choice => 'upgrade',
                    default => null,
                };
                if ($requiredChoiceType !== null) {
                    $crew = CampaignCrew::find($leader->campaign_crew_id);
                    $options = $crew ? AftermathCatalog::crewCardChoiceOptions($crew)[$requiredChoiceType.'s'] : [];
                    $picked = collect($options)->firstWhere('id', $a['crew_card_choice']['id'] ?? null);
                    if (! $picked) {
                        return 'This crew card requires choosing a '.$requiredChoiceType.' from a crew card of a master sharing your keywords.';
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolves and validates the Attack/Tactical Mod target (pg 31, 38-43),
     * then checks the target action's current Skl against a skl_boost row's
     * qualifying range. Returns an error string, or null if everything checks
     * out.
     *
     * @param  array<string, mixed>  $a
     */
    private function validateAttackTacticalTarget(CustomCharacter $leader, array $a, AdvancementAttackMod|AdvancementTacticalMod $row): ?string
    {
        $appliedToCustomCharacterId = $a['applied_to_custom_character_id'] ?? null;
        $fromEquipmentId = $a['from_equipment_id'] ?? null;
        $actionIndex = $a['applied_to_action_index'] ?? null;
        $appliedToActionId = $a['applied_to_action_id'] ?? null;

        $targetSkl = null;
        if ($fromEquipmentId !== null) {
            $equipment = CampaignEquipment::query()
                ->whereKey($fromEquipmentId)
                ->where('campaign_crew_id', $leader->campaign_crew_id)
                ->active()
                ->with('catalog.actions')
                ->first();
            if (! $equipment) {
                return 'Selected Equipment is not part of this crew.';
            }
            $grantedAction = $appliedToActionId !== null
                ? $equipment->catalog?->actions->firstWhere('id', $appliedToActionId)
                : null;
            if (! $grantedAction) {
                return 'Selected action is not granted by that Equipment.';
            }
            $targetSkl = $grantedAction->stat;
        } elseif ($appliedToCustomCharacterId !== null) {
            $totem = CustomCharacter::query()
                ->whereKey($appliedToCustomCharacterId)
                ->where('campaign_crew_id', $leader->campaign_crew_id)
                ->where('is_campaign_totem', true)
                ->where('current', true)
                ->first();
            if (! $totem) {
                return "Selected Totem doesn't belong to this crew.";
            }
            $targetSkl = ($actionIndex !== null && isset($totem->actions[$actionIndex])) ? ($totem->actions[$actionIndex]['stat'] ?? null) : null;
        } else {
            $actions = $leader->actions ?? [];
            $targetSkl = ($actionIndex !== null && isset($actions[$actionIndex])) ? ($actions[$actionIndex]['stat'] ?? null) : null;
        }

        // Skl Boost qualifying range (pg 38-43): "select one [attack/tactical]
        // action with a Skl of X [or Y]" — the target's current Skl must fall
        // within the row's [skl_from, skl_from_max ?? skl_from] range.
        if ($row->modifier_type === 'skl_boost') {
            $min = (int) $row->skl_from;
            $max = (int) ($row->skl_from_max ?? $row->skl_from);
            if ($targetSkl === null || (int) $targetSkl < $min || (int) $targetSkl > $max) {
                return $min === $max
                    ? "That Skl Boost requires an action with a Skl of {$min}."
                    : "That Skl Boost requires an action with a Skl of {$min}–{$max}.";
            }
        }

        return null;
    }

    /**
     * Persist the advancement records against the leader and apply the
     * mechanical effect to the leader's card data (actions/abilities JSON).
     *
     * @param  array<int, array<string, mixed>>  $advancements
     */
    public function create(CustomCharacter $leader, array $advancements, ?int $sourceAftermathId): void
    {
        foreach ($advancements as $a) {
            $table = AdvancementTableEnum::from($a['source_table']);
            $catalogId = isset($a['catalog_id']) ? (int) $a['catalog_id'] : null;
            $actionIndex = (int) ($a['applied_to_action_index'] ?? -1);

            $advancementRow = $this->resolveAdvancementRow($table, $catalogId);
            $freeChoice = is_array($a['free_choice'] ?? null) ? $a['free_choice'] : null;
            $coreCatalogId = $this->resolveCoreCatalogId($table, $catalogId, $advancementRow, $freeChoice);

            // Attack/Tactical Mod target (pg 31, 38-43): resolves to the Leader
            // (default), the crew's current Totem, or null for Equipment —
            // Equipment has no per-instance actions[] to mutate, so the
            // CampaignLeaderAdvancement row below is the sole record of the
            // effect and nothing gets applied mechanically.
            $appliedToCustomCharacterId = isset($a['applied_to_custom_character_id']) ? (int) $a['applied_to_custom_character_id'] : null;
            $fromEquipmentId = isset($a['from_equipment_id']) ? (int) $a['from_equipment_id'] : null;
            $appliedToActionId = isset($a['applied_to_action_id']) ? (int) $a['applied_to_action_id'] : null;
            $targetCharacter = match (true) {
                $fromEquipmentId !== null => null,
                $appliedToCustomCharacterId !== null => CustomCharacter::find($appliedToCustomCharacterId),
                default => $leader,
            };

            // Skl Boost (pg 38-43): capture the target's actual current Skl
            // before mutating it, so removing the advancement later restores
            // exactly what was there — the catalog row's skl_from is a
            // qualifying range (e.g. "Skl of 0 or 1"), not necessarily the
            // action's exact prior value. N/A for Equipment (nothing mutated).
            $appliedSklFrom = null;
            if (
                $targetCharacter
                && ($advancementRow instanceof AdvancementAttackMod || $advancementRow instanceof AdvancementTacticalMod)
                && $advancementRow->modifier_type === 'skl_boost'
                && $actionIndex >= 0
            ) {
                $appliedSklFrom = $targetCharacter->actions[$actionIndex]['stat'] ?? null;
            }

            CampaignLeaderAdvancement::create([
                'custom_character_id' => $leader->id,
                'source_aftermath_id' => $sourceAftermathId,
                'source_table' => $a['source_table'],
                'advancement_catalog_id' => $catalogId,
                'catalog_core_id' => $coreCatalogId,
                'from_equipment_id' => $fromEquipmentId,
                'applied_to_action_index' => $actionIndex,
                'applied_to_action_id' => $appliedToActionId,
                'applied_to_custom_character_id' => $appliedToCustomCharacterId,
                'applied_skl_from' => $appliedSklFrom,
                'position_in_xp_track' => $a['position_in_xp_track'],
                'free_choice' => $a['free_choice'] ?? null,
                'acquired_at' => now(),
            ]);

            match ($table) {
                AdvancementTableEnum::Totem => $this->createTotemFromTemplate(
                    $leader,
                    $catalogId ?? 0,
                    $a['totem_name'] ?? null,
                    isset($a['totem_size']) ? (int) $a['totem_size'] : null,
                    $a['totem_base'] ?? null,
                ),
                AdvancementTableEnum::Summoning => ($targetCharacter && $coreCatalogId)
                    ? $this->applyActionToLeader($targetCharacter, $coreCatalogId)
                    : null,
                AdvancementTableEnum::Action => $advancementRow instanceof AdvancementAction
                    ? $this->applyAdvancementAction($leader, $advancementRow, $freeChoice)
                    : null,
                AdvancementTableEnum::Ability => ($targetCharacter && $advancementRow instanceof AdvancementAbility)
                    ? $this->applyAdvancementAbility($targetCharacter, $advancementRow, $freeChoice)
                    : null,
                AdvancementTableEnum::AttackMod, AdvancementTableEnum::TacticalMod => ($targetCharacter && ($advancementRow instanceof AdvancementAttackMod || $advancementRow instanceof AdvancementTacticalMod))
                    ? $this->applyAdvancementModifier($targetCharacter, $advancementRow, $actionIndex)
                    : null,
                AdvancementTableEnum::CrewCard => $catalogId
                    ? $this->applyCrewCardAdvancement(
                        $leader,
                        $catalogId,
                        $freeChoice,
                        $sourceAftermathId,
                        is_array($a['crew_card_choice'] ?? null) ? $a['crew_card_choice'] : null,
                    )
                    : null,
                default => null,
            };
        }
    }

    /**
     * Tier-4 Crew Card (pg 32, 54): the picked effect stacks alongside the
     * crew's starter effect rather than replacing it — see
     * CampaignCrewCardAdvancement.
     *
     * @param  array<string, mixed>|null  $freeChoice
     * @param  array<string, mixed>|null  $crewCardChoiceInput
     */
    private function applyCrewCardAdvancement(
        CustomCharacter $leader,
        int $catalogId,
        ?array $freeChoice,
        ?int $sourceAftermathId,
        ?array $crewCardChoiceInput,
    ): void {
        if ($leader->campaign_crew_id === null) {
            return;
        }

        $crewCard = CampaignCrewCard::find($catalogId);

        CampaignCrewCardAdvancement::create([
            'campaign_crew_id' => $leader->campaign_crew_id,
            'crew_card_effect_id' => $catalogId,
            'source_master_id' => $crewCard->master_id ?? ($freeChoice['source_character_id'] ?? null),
            'crew_card_choice' => $this->resolveCrewCardChoice($leader->campaign_crew_id, $catalogId, $crewCardChoiceInput),
            'acquired_aftermath_id' => $sourceAftermathId,
        ]);
    }

    /**
     * Re-resolves the picked token/marker/upgrade-type against the
     * constrained pool (validate() already confirmed it's valid) so the
     * persisted row stores the canonical { type, id, name } shape, not raw
     * client input.
     *
     * @param  array<string, mixed>|null  $input
     * @return array{type: string, id: int|string, name: string}|null
     */
    private function resolveCrewCardChoice(int $campaignCrewId, int $catalogId, ?array $input): ?array
    {
        $crewCard = CampaignCrewCard::find($catalogId);
        $requiredType = match (true) {
            (bool) $crewCard?->requires_token_choice => 'token',
            (bool) $crewCard?->requires_marker_choice => 'marker',
            (bool) $crewCard?->requires_upgrade_type_choice => 'upgrade',
            default => null,
        };
        if ($requiredType === null) {
            return null;
        }

        $crew = CampaignCrew::find($campaignCrewId);
        $options = $crew ? AftermathCatalog::crewCardChoiceOptions($crew)[$requiredType.'s'] : [];
        $picked = collect($options)->firstWhere('id', $input['id'] ?? null);

        return $picked ? ['type' => $requiredType, 'id' => $picked['id'], 'name' => $picked['name']] : null;
    }

    private function resolveAdvancementRow(AdvancementTableEnum $table, ?int $catalogId): ?Model
    {
        if ($catalogId === null) {
            return null;
        }

        return match ($table) {
            AdvancementTableEnum::AttackMod => AdvancementAttackMod::find($catalogId),
            AdvancementTableEnum::TacticalMod => AdvancementTacticalMod::find($catalogId),
            AdvancementTableEnum::Action => AdvancementAction::find($catalogId),
            AdvancementTableEnum::Ability => AdvancementAbility::find($catalogId),
            default => null,
        };
    }

    /**
     * The id of the real catalog row the leader's card data was actually
     * built from — used for undo. For Attack/Tactical Mod/Action/Ability,
     * that's the row's Lookup FK when set, else the advancement row's own id
     * (a bespoke campaign-only entry has no real catalog analog to point
     * at). An Any Joker row's core id is the player's free-chosen source
     * instead. Totem/Summoning/CrewCard are unaffected by this table split —
     * their catalog_id already points at the real core catalog row.
     *
     * @param  array<string, mixed>|null  $freeChoice
     */
    private function resolveCoreCatalogId(AdvancementTableEnum $table, ?int $catalogId, ?Model $advancementRow, ?array $freeChoice): ?int
    {
        if (($advancementRow instanceof AdvancementAction || $advancementRow instanceof AdvancementAbility) && $advancementRow->is_joker) {
            return isset($freeChoice['source_id']) ? (int) $freeChoice['source_id'] : null;
        }

        return match (true) {
            $advancementRow instanceof AdvancementAttackMod, $advancementRow instanceof AdvancementTacticalMod => $advancementRow->trigger_id ?? $advancementRow->id,
            $advancementRow instanceof AdvancementAction => $advancementRow->action_id ?? $advancementRow->id,
            $advancementRow instanceof AdvancementAbility => $advancementRow->ability_id ?? $advancementRow->id,
            default => $catalogId,
        };
    }

    /**
     * @param  array<string, mixed>|null  $freeChoice
     */
    private function applyAdvancementAction(CustomCharacter $leader, AdvancementAction $row, ?array $freeChoice): void
    {
        if ($row->is_joker) {
            if (isset($freeChoice['source_id'])) {
                $this->applyActionToLeader($leader, (int) $freeChoice['source_id'], $this->freeChoiceActionIsSignature($freeChoice));
            }

            return;
        }

        if ($row->action_id) {
            $this->applyActionToLeader($leader, $row->action_id, (bool) $row->is_signature);

            return;
        }

        $stat = $row->stat_block ?? [];
        $actions = $leader->actions ?? [];
        $actions[] = [
            'name' => $row->talent_name,
            'type' => $stat['type'] ?? 'tactical',
            'category' => $stat['type'] ?? 'tactical',
            'is_signature' => (bool) $row->is_signature,
            'stone_cost' => 0,
            'range' => $stat['range'] ?? null,
            'range_type' => $stat['range_type'] ?? null,
            'stat' => $stat['stat'] ?? null,
            'stat_suits' => $stat['stat_suits'] ?? null,
            'stat_modifier' => $stat['stat_modifier'] ?? null,
            'resisted_by' => $stat['resisted_by'] ?? null,
            'target_number' => $stat['target_number'] ?? null,
            'target_suits' => $stat['target_suits'] ?? null,
            'damage' => $stat['damage'] ?? null,
            'description' => $row->effect_text,
            'source_id' => $row->id,
            'triggers' => [],
        ];
        $leader->actions = $actions;
        $leader->save();
    }

    /**
     * $target is the Leader (default) or the crew's current Totem when the
     * advancement was routed there (pg 31) — same routing as Attack/Tactical
     * Mod.
     *
     * @param  array<string, mixed>|null  $freeChoice
     */
    private function applyAdvancementAbility(CustomCharacter $target, AdvancementAbility $row, ?array $freeChoice): void
    {
        if ($row->is_joker) {
            if (isset($freeChoice['source_id'])) {
                $this->applyAbilityToTarget($target, (int) $freeChoice['source_id']);
            }

            return;
        }

        if ($row->ability_id) {
            $this->applyAbilityToTarget($target, $row->ability_id);

            return;
        }

        $abilities = $target->abilities ?? [];
        $abilities[] = [
            'name' => $row->talent_name,
            'suits' => $row->suits,
            'defensive_ability_type' => $row->defensive_ability_type,
            'costs_stone' => false,
            'description' => $row->effect_text,
            'source_id' => $row->id,
        ];
        $target->abilities = $abilities;
        $target->save();
    }

    /**
     * $target is the Leader (default) or the crew's current Totem when the
     * advancement was routed there (pg 31) — both share the identical
     * actions[] mechanism, so the same mutation logic applies to either.
     */
    private function applyAdvancementModifier(CustomCharacter $target, AdvancementAttackMod|AdvancementTacticalMod $row, int $actionIndex): void
    {
        match ($row->modifier_type) {
            'skl_boost' => $this->applySklBoostToTarget($target, $actionIndex, $row->skl_to),
            'signature' => $this->applySignatureToTarget($target, $actionIndex),
            default => $row->trigger_id
                ? $this->applyTriggerToTarget($target, $row->trigger_id, $actionIndex)
                : $this->applyBespokeTriggerToTarget($target, $row, $actionIndex),
        };
    }

    private function applySklBoostToTarget(CustomCharacter $target, int $actionIndex, ?int $sklTo): void
    {
        if ($actionIndex < 0 || $sklTo === null) {
            return;
        }

        $actions = $target->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['stat'] = $sklTo;
        $target->actions = $actions;
        $target->save();
    }

    private function applySignatureToTarget(CustomCharacter $target, int $actionIndex): void
    {
        if ($actionIndex < 0) {
            return;
        }

        $actions = $target->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['is_signature'] = true;
        $target->actions = $actions;
        $target->save();
    }

    private function applyBespokeTriggerToTarget(CustomCharacter $target, AdvancementAttackMod|AdvancementTacticalMod $row, int $actionIndex): void
    {
        if ($actionIndex < 0) {
            return;
        }

        $actions = $target->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['triggers'][] = [
            'name' => $row->name,
            'suits' => $row->suit,
            'stone_cost' => 0,
            'description' => $row->effect_text,
            'source_id' => $row->id,
        ];
        $target->actions = $actions;
        $target->save();
    }

    /**
     * Any Joker (pg 31): "The chosen action retains the same skill (Skl)
     * and any signature symbol" — inherited from whether it was a
     * Signature Action on the ally it was copied from, not a player
     * choice.
     *
     * @param  array<string, mixed>|null  $freeChoice
     */
    private function freeChoiceActionIsSignature(?array $freeChoice): bool
    {
        $sourceId = $freeChoice['source_id'] ?? null;
        $sourceCharacterId = $freeChoice['source_character_id'] ?? null;
        if ($sourceId === null || $sourceCharacterId === null) {
            return false;
        }

        $character = Character::find($sourceCharacterId);

        return (bool) $character?->actions()->whereKey($sourceId)->first()?->pivot->is_signature_action; // @phpstan-ignore property.notFound (pivot from morphedByMany)
    }

    private function applyActionToLeader(CustomCharacter $leader, int $actionId, bool $isSignature = false): void
    {
        $action = Action::with('triggers:id,name,suits,stone_cost,description')->find($actionId);
        if (! $action) {
            return;
        }

        $typeValue = $action->type instanceof \BackedEnum ? $action->type->value : (string) $action->type;
        $actions = $leader->actions ?? [];
        $actions[] = [
            'name' => $action->name,
            'type' => $typeValue,
            'category' => $typeValue,
            'is_signature' => $isSignature,
            'stone_cost' => $action->stone_cost ?? 0,
            'range' => $action->range,
            'range_type' => $action->range_type instanceof \BackedEnum ? $action->range_type->value : $action->range_type,
            'stat' => $action->stat,
            'stat_suits' => $action->stat_suits,
            'stat_modifier' => $action->stat_modifier instanceof \BackedEnum ? $action->stat_modifier->value : $action->stat_modifier,
            'resisted_by' => $action->resisted_by,
            'target_number' => $action->target_number,
            'target_suits' => $action->target_suits,
            'damage' => $action->damage,
            'description' => $action->description,
            'source_id' => $action->id,
            'triggers' => $action->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
                'source_id' => $t->id,
            ])->all(),
        ];
        $leader->actions = $actions;
        $leader->save();
    }

    private function applyAbilityToTarget(CustomCharacter $target, int $abilityId): void
    {
        $ability = Ability::find($abilityId);
        if (! $ability) {
            return;
        }

        $abilities = $target->abilities ?? [];
        $abilities[] = [
            'name' => $ability->name,
            'suits' => $ability->suits,
            'defensive_ability_type' => $ability->defensive_ability_type instanceof \BackedEnum
                ? $ability->defensive_ability_type->value
                : $ability->defensive_ability_type,
            'costs_stone' => (bool) $ability->costs_stone,
            'description' => $ability->description,
            'source_id' => $ability->id,
        ];
        $target->abilities = $abilities;
        $target->save();
    }

    private function applyTriggerToTarget(CustomCharacter $target, int $triggerId, int $actionIndex): void
    {
        if ($actionIndex < 0) {
            return;
        }

        $trigger = Trigger::find($triggerId);
        if (! $trigger) {
            return;
        }

        $actions = $target->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['triggers'][] = [
            'name' => $trigger->name,
            'suits' => $trigger->suits,
            'stone_cost' => $trigger->stone_cost ?? 0,
            'description' => $trigger->description,
            'source_id' => $trigger->id,
        ];
        $target->actions = $actions;
        $target->save();
    }

    private function createTotemFromTemplate(
        CustomCharacter $leader,
        int $templateId,
        ?string $totemName,
        ?int $totemSize,
        ?string $totemBase,
    ): void {
        $template = CustomCharacter::query()
            ->where('is_campaign_totem_template', true)
            ->whereKey($templateId)
            ->with(['campaignTotemActions.triggers', 'campaignTotemAbilities'])
            ->first();

        if (! $template) {
            return;
        }

        $actions = $template->campaignTotemActions->map(fn (Action $action) => [
            'name' => $action->name,
            'type' => $action->type ?? 'tactical',
            'category' => $action->type ?? 'tactical',
            'is_signature' => (bool) ($action->pivot->is_signature_action ?? false),
            'stone_cost' => $action->stone_cost ?? 0,
            'range' => $action->range,
            'range_type' => $action->range_type,
            'stat' => $action->stat,
            'stat_suits' => $action->stat_suits,
            'stat_modifier' => $action->stat_modifier,
            'resisted_by' => $action->resisted_by,
            'target_number' => $action->target_number,
            'target_suits' => $action->target_suits,
            'damage' => $action->damage,
            'description' => $action->description,
            'source_id' => $action->id,
            'triggers' => $action->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
            ])->all(),
        ])->all();

        $abilities = $template->campaignTotemAbilities->map(fn (Ability $ab) => [
            'name' => $ab->name,
            'description' => $ab->description,
            'suits' => $ab->suits,
            'source_id' => $ab->id,
        ])->all();

        // BaseSizeEnum is int-backed (30/40/50); convert the user's label string,
        // falling back to the template's own base if the player didn't specify one.
        $baseInt = match ($totemBase) {
            '40mm' => 40,
            '50mm' => 50,
            '30mm' => 30,
            default => $template->base instanceof \BackedEnum
                ? (int) $template->base->value
                : (int) ($template->base ?? 30),
        };

        CustomCharacter::create([
            'user_id' => $leader->user_id,
            'campaign_crew_id' => $leader->campaign_crew_id,
            'is_campaign_leader' => false,
            'is_campaign_totem' => true,
            'current' => true,
            'name' => $totemName ?: $template->name,
            'faction' => $leader->faction,
            'station' => null,
            'cost' => null,
            'health' => $template->health ?? 0,
            'defense' => $template->defense ?? 0,
            'defense_suit' => $template->defense_suit instanceof \BackedEnum
                ? $template->defense_suit->value
                : $template->defense_suit,
            'willpower' => $template->willpower ?? 0,
            'willpower_suit' => $template->willpower_suit instanceof \BackedEnum
                ? $template->willpower_suit->value
                : $template->willpower_suit,
            'speed' => $template->speed ?? 0,
            'size' => $totemSize ?? $template->size ?? 1,
            'base' => $baseInt,
            'is_unhirable' => true,
            'actions' => $actions,
            'abilities' => $abilities,
            // "Your crew now hires the totem for free... its keywords match
            // your leader's" (pg 32).
            'keywords' => $leader->keywords ?? [],
            'characteristics' => [],
        ]);
    }

    /**
     * Reverses a single advancement's mechanical effect — the caller still
     * owns deleting the `CampaignLeaderAdvancement` row itself afterward.
     * Shared by the Arsenal Sheet's one-at-a-time remove
     * (`LeaderAdvancementController::destroy()`) and the Aftermath wizard's
     * bulk "go back a phase" undo, so both stay in sync with whatever
     * `create()` above actually applied.
     */
    public function revertAdvancement(CustomCharacter $leader, CampaignLeaderAdvancement $advancement, CampaignCrew $crew): void
    {
        if ($advancement->source_table === AdvancementTableEnum::Totem) {
            // Tear down the crew's active totem so the next Totem advancement
            // can be taken and crewCardEffect stays in sync.
            CustomCharacter::query()
                ->where('campaign_crew_id', $crew->id)
                ->where('is_campaign_totem', true)
                ->where('current', true)
                ->delete();

            return;
        }

        if ($advancement->source_table === AdvancementTableEnum::CrewCard) {
            // Crew Card advancements stack onto CampaignCrewCardAdvancement,
            // not the leader's actions/abilities JSON — remove the matching row.
            $rowId = CampaignCrewCardAdvancement::query()
                ->where('campaign_crew_id', $crew->id)
                ->where('crew_card_effect_id', $advancement->advancement_catalog_id)
                ->latest('id')
                ->value('id');
            if ($rowId) {
                CampaignCrewCardAdvancement::destroy($rowId);
            }

            return;
        }

        $this->undoAdvancement($leader, $advancement);
    }

    /**
     * Reverse the mechanical effect that was applied to the leader's card data
     * when the advancement was first logged (mirror of `create()` above).
     * Removes the first matching entry so duplicate source_ids aren't over-removed.
     */
    private function undoAdvancement(CustomCharacter $leader, CampaignLeaderAdvancement $advancement): void
    {
        $table = $advancement->source_table;
        $catalogId = $advancement->catalog_core_id;

        if ($table === AdvancementTableEnum::AttackMod || $table === AdvancementTableEnum::TacticalMod) {
            // Equipment-targeted advancements never mutated any actions[] —
            // equipment has no per-instance one to mutate, so the record being
            // deleted (by the caller, right after this returns) is the entire
            // undo; nothing mechanical to reverse.
            if ($advancement->from_equipment_id !== null) {
                return;
            }

            $target = $advancement->applied_to_custom_character_id !== null
                ? CustomCharacter::find($advancement->applied_to_custom_character_id)
                : $leader;
            if (! $target) {
                return;
            }

            $idx = $advancement->applied_to_action_index;
            if ($idx < 0) {
                return;
            }

            $modifierType = $this->modifierTypeFor($table, $advancement->advancement_catalog_id);

            if ($modifierType === 'skl_boost') {
                $this->undoSklBoost($target, $advancement->applied_skl_from, $idx);

                return;
            }

            if ($modifierType === 'signature') {
                $this->undoSignature($target, $idx);

                return;
            }

            if ($catalogId === null) {
                return;
            }
            $actions = $target->actions ?? [];
            if (! isset($actions[$idx])) {
                return;
            }
            $removed = false;
            $actions[$idx]['triggers'] = array_values(array_filter(
                $actions[$idx]['triggers'] ?? [],
                function (array $t) use ($catalogId, &$removed): bool {
                    if (! $removed && ($t['source_id'] ?? null) === $catalogId) {
                        $removed = true;

                        return false;
                    }

                    return true;
                }
            ));
            $target->actions = $actions;
            $target->save();

            return;
        }

        if ($table === AdvancementTableEnum::Action) {
            if ($catalogId === null) {
                return;
            }
            $removed = false;
            $leader->actions = array_values(array_filter(
                $leader->actions ?? [],
                function (array $a) use ($catalogId, &$removed): bool {
                    if (! $removed && ($a['source_id'] ?? null) === $catalogId) {
                        $removed = true;

                        return false;
                    }

                    return true;
                }
            ));
            $leader->save();

            return;
        }

        if ($table === AdvancementTableEnum::Summoning) {
            if ($catalogId === null) {
                return;
            }
            $target = $advancement->applied_to_custom_character_id !== null
                ? CustomCharacter::find($advancement->applied_to_custom_character_id)
                : $leader;
            if (! $target) {
                return;
            }
            $removed = false;
            $target->actions = array_values(array_filter(
                $target->actions ?? [],
                function (array $a) use ($catalogId, &$removed): bool {
                    if (! $removed && ($a['source_id'] ?? null) === $catalogId) {
                        $removed = true;

                        return false;
                    }

                    return true;
                }
            ));
            $target->save();

            return;
        }

        if ($table === AdvancementTableEnum::Ability) {
            if ($catalogId === null) {
                return;
            }
            $target = $advancement->applied_to_custom_character_id !== null
                ? CustomCharacter::find($advancement->applied_to_custom_character_id)
                : $leader;
            if (! $target) {
                return;
            }
            $removed = false;
            $target->abilities = array_values(array_filter(
                $target->abilities ?? [],
                function (array $a) use ($catalogId, &$removed): bool {
                    if (! $removed && ($a['source_id'] ?? null) === $catalogId) {
                        $removed = true;

                        return false;
                    }

                    return true;
                }
            ));
            $target->save();
        }
    }

    private function modifierTypeFor(AdvancementTableEnum $table, ?int $advancementCatalogId): ?string
    {
        if ($advancementCatalogId === null) {
            return null;
        }

        return match ($table) {
            AdvancementTableEnum::AttackMod => AdvancementAttackMod::query()->whereKey($advancementCatalogId)->value('modifier_type'),
            AdvancementTableEnum::TacticalMod => AdvancementTacticalMod::query()->whereKey($advancementCatalogId)->value('modifier_type'),
            default => null,
        };
    }

    /**
     * Reverts a Skl Boost to the action's actual prior Skl, captured on the
     * advancement record when it was applied — the catalog row's skl_from is
     * a qualifying range (e.g. "Skl of 0 or 1"), not necessarily the exact
     * value the action had, so it can't be used to restore it.
     */
    private function undoSklBoost(CustomCharacter $target, ?int $appliedSklFrom, int $actionIndex): void
    {
        if ($appliedSklFrom === null) {
            return;
        }

        $actions = $target->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['stat'] = $appliedSklFrom;
        $target->actions = $actions;
        $target->save();
    }

    private function undoSignature(CustomCharacter $target, int $actionIndex): void
    {
        $actions = $target->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['is_signature'] = false;
        $target->actions = $actions;
        $target->save();
    }
}
