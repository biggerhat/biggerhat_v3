<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreLeaderAdvancementRequest;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\CustomCharacter;
use App\Services\Campaign\LeaderAdvancementService;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;

/**
 * Log / remove a Leadership-Experience advancement against the crew's leader
 * straight from the Arsenal Sheet (pg 31). Advancements are also taken during
 * the Aftermath's Advance-Leader step; both go through LeaderAdvancementService
 * so the rules + record shape stay identical.
 */
class LeaderAdvancementController extends Controller
{
    use AuthorizesCampaignAccess;

    public function store(StoreLeaderAdvancementRequest $request, Campaign $campaign, CampaignCrew $crew, LeaderAdvancementService $service)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $leader = $this->currentLeader($crew);
        if (! $leader) {
            return redirect()->back()->withMessage('No active leader to advance — build one first.', null, MessageTypeEnum::error);
        }

        $data = $request->validated();
        $position = (int) $data['position_in_xp_track'];

        // The box must be earned (filled) and grant an advancement (numbered tier),
        // and not already hold one — pick a different box or remove it first.
        $box = collect($leader->xp_track ?? CustomCharacter::defaultXpTrack())->firstWhere('index', $position);
        if (! $box || empty($box['filled']) || ($box['tier'] ?? null) === null) {
            return redirect()->back()->withMessage('That experience box has not been earned yet, or grants no advancement.', null, MessageTypeEnum::error);
        }
        $alreadyTaken = CampaignLeaderAdvancement::query()
            ->where('custom_character_id', $leader->id)
            ->where('position_in_xp_track', $position)
            ->exists();
        if ($alreadyTaken) {
            return redirect()->back()->withMessage('That box already has an advancement — remove it first to change it.', null, MessageTypeEnum::error);
        }

        $rejection = $service->validate($leader, [$data]);
        if ($rejection !== null) {
            return redirect()->back()->withMessage($rejection, null, MessageTypeEnum::error);
        }

        // source_aftermath_id is null — this was logged directly, not via an aftermath.
        $service->create($leader, [$data], null);

        return redirect()->back()->withMessage('Advancement logged.');
    }

    public function destroy(Request $request, Campaign $campaign, CampaignCrew $crew, CampaignLeaderAdvancement $advancement)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $leader = $this->currentLeader($crew);
        if (! $leader || $advancement->custom_character_id !== $leader->id) {
            abort(403);
        }

        // Totem advancement removal: tear down the crew's active totem so the
        // next Totem advancement can be taken and crewCardEffect stays in sync.
        if ($advancement->source_table === AdvancementTableEnum::Totem) {
            CustomCharacter::query()
                ->where('campaign_crew_id', $crew->id)
                ->where('is_campaign_totem', true)
                ->where('current', true)
                ->delete();
        } else {
            $this->undoAdvancement($leader, $advancement);
        }

        $advancement->delete();

        return redirect()->back()->withMessage('Advancement removed.');
    }

    /**
     * Reverse the mechanical effect that was applied to the leader's card data
     * when the advancement was first logged (mirror of LeaderAdvancementService).
     * Removes the first matching entry so duplicate source_ids aren't over-removed.
     */
    private function undoAdvancement(CustomCharacter $leader, CampaignLeaderAdvancement $advancement): void
    {
        $table = $advancement->source_table;
        $catalogId = $advancement->catalog_core_id;

        if ($table === AdvancementTableEnum::AttackMod || $table === AdvancementTableEnum::TacticalMod) {
            $idx = $advancement->applied_to_action_index;
            if ($idx < 0) {
                return;
            }

            $modifierType = $this->modifierTypeFor($table, $advancement->advancement_catalog_id);

            if ($modifierType === 'skl_boost') {
                $this->undoSklBoost($leader, $table, $advancement->advancement_catalog_id, $idx);

                return;
            }

            if ($modifierType === 'signature') {
                $this->undoSignature($leader, $idx);

                return;
            }

            if ($catalogId === null) {
                return;
            }
            $actions = $leader->actions ?? [];
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
            $leader->actions = $actions;
            $leader->save();

            return;
        }

        if ($table === AdvancementTableEnum::Action || $table === AdvancementTableEnum::Summoning) {
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

        if ($table === AdvancementTableEnum::Ability) {
            if ($catalogId === null) {
                return;
            }
            $removed = false;
            $leader->abilities = array_values(array_filter(
                $leader->abilities ?? [],
                function (array $a) use ($catalogId, &$removed): bool {
                    if (! $removed && ($a['source_id'] ?? null) === $catalogId) {
                        $removed = true;

                        return false;
                    }

                    return true;
                }
            ));
            $leader->save();
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

    private function undoSklBoost(CustomCharacter $leader, AdvancementTableEnum $table, ?int $advancementCatalogId, int $actionIndex): void
    {
        if ($advancementCatalogId === null) {
            return;
        }

        $sklFrom = match ($table) {
            AdvancementTableEnum::AttackMod => AdvancementAttackMod::query()->whereKey($advancementCatalogId)->value('skl_from'),
            AdvancementTableEnum::TacticalMod => AdvancementTacticalMod::query()->whereKey($advancementCatalogId)->value('skl_from'),
            default => null,
        };
        if ($sklFrom === null) {
            return;
        }

        $actions = $leader->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['stat'] = $sklFrom;
        $leader->actions = $actions;
        $leader->save();
    }

    private function undoSignature(CustomCharacter $leader, int $actionIndex): void
    {
        $actions = $leader->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['is_signature'] = false;
        $leader->actions = $actions;
        $leader->save();
    }

    private function currentLeader(CampaignCrew $crew): ?CustomCharacter
    {
        return CustomCharacter::query()
            ->where('campaign_crew_id', $crew->id)
            ->where('is_campaign_leader', true)
            ->where('current', true)
            ->first();
    }
}
