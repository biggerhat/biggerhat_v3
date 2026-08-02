<?php

namespace App\Observers;

use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\Campaign\CampaignArsenalModel;

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

        GenerateCombinedCrewCardImage::dispatch($model->campaign_crew_id)->afterCommit();
    }
}
