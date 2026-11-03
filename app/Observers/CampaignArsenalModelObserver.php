<?php

namespace App\Observers;

use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignEquipment;

/**
 * Regenerates a crew's combined Crew Card back face (tokens/markers gathered
 * from the crew's currently-active arsenal — see
 * CombinedCrewCardEffects::arsenalTokensAndMarkers()) whenever the arsenal
 * roster actually changes. Every mutation path (Weekly Hire, ad-hoc add,
 * Cut 'Em Up scrap, Aftermath annihilation/Traitor defection, an Aftermath
 * undo reviving a model) funnels through here instead of each controller
 * remembering to dispatch itself — mirrors CustomCharacterObserver's
 * GenerateLeaderCardImage hook.
 */
class CampaignArsenalModelObserver
{
    public function created(CampaignArsenalModel $model): void
    {
        GenerateCombinedCrewCardImage::dispatch($model->campaign_crew_id)->afterCommit();
    }

    public function updated(CampaignArsenalModel $model): void
    {
        if (! $model->wasChanged(['annihilated_at', 'removed_at'])) {
            return;
        }

        // A bound (Corrupted Pawns) item lives and dies with its model (pg 146); an undo that revives the model restores it.
        if ($model->wasChanged('annihilated_at')) {
            CampaignEquipment::query()
                ->where('bound_arsenal_model_id', $model->id)
                ->update(['annihilated_at' => $model->annihilated_at]);
        }

        GenerateCombinedCrewCardImage::dispatch($model->campaign_crew_id)->afterCommit();
    }
}
