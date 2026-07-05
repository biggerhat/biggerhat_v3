<?php

namespace App\Traits\Campaign;

use App\Enums\CharacterStationEnum;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Character;
use Illuminate\Support\Str;

/**
 * Titled models (pg 18): every titled version of a character shares a
 * catalog-side `title_group_key`. Hiring any one of them auto-adds the rest
 * to the arsenal — all sharing one freshly-generated arsenal-side
 * title_group_key so the existing injury-cascade in
 * CampaignArsenalModel::attachInjury() treats them as one unit. Used by both
 * WeeklyHireController and StartingArsenalController's hire flows.
 */
trait HiresTitledModelGroup
{
    /**
     * Creates the arsenal-model rows for a titled group's non-hired siblings
     * and re-points the just-created hired row onto the shared group key.
     * No-op when the hired character isn't part of a title group.
     */
    private function addTitledSiblings(CampaignArsenalModel $hiredModel, Character $character, int $crewId, ?int $acquiredWeek): void
    {
        if ($character->title_group_key === null) {
            return;
        }

        $groupKey = (string) Str::uuid();
        $hiredModel->update(['title_group_key' => $groupKey]);

        $siblings = Character::query()
            ->where('title_group_key', $character->title_group_key)
            ->where('id', '!=', $character->id)
            ->get(['id', 'station']);

        foreach ($siblings as $sibling) {
            CampaignArsenalModel::create([
                'campaign_crew_id' => $crewId,
                'character_id' => $sibling->id,
                'is_peon' => $sibling->station === CharacterStationEnum::Peon,
                'title_group_key' => $groupKey,
                'acquired_week' => $acquiredWeek,
                'acquired_via' => 'hire',
            ]);
        }
    }
}
