<?php

namespace App\Observers;

use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\CustomUpgrade;
use Illuminate\Support\Str;

class CustomUpgradeObserver
{
    public function creating(CustomUpgrade $upgrade): void
    {
        $upgrade->display_name = $upgrade->name;

        $base = Str::slug($upgrade->display_name);
        $slug = $base;
        $i = 1;
        while (CustomUpgrade::where('slug', $slug)->where('user_id', $upgrade->user_id)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        $upgrade->slug = $slug;
    }

    public function updating(CustomUpgrade $upgrade): void
    {
        if ($upgrade->isDirty('name')) {
            $upgrade->display_name = $upgrade->name;

            $base = Str::slug($upgrade->display_name);
            $slug = $base;
            $i = 1;
            while (CustomUpgrade::where('slug', $slug)->where('user_id', $upgrade->user_id)->where('id', '!=', $upgrade->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $upgrade->slug = $slug;
        }
    }

    /**
     * A player editing their crew card's content via the normal Card
     * Creator editor (CustomUpgradeController::update()) needs the
     * generated Crew Card image to reflect it — mirrors
     * CampaignArsenalModelObserver's identical regenerate-on-change hook.
     * StartingArsenalController::update() already dispatches this itself
     * right after the INITIAL save-to-Card-Creator, so this only needs to
     * cover later edits.
     */
    public function updated(CustomUpgrade $upgrade): void
    {
        if ($upgrade->is_campaign_crew_card && $upgrade->campaign_crew_id && $upgrade->wasChanged('content_blocks')) {
            GenerateCombinedCrewCardImage::dispatch($upgrade->campaign_crew_id)->afterCommit();
        }
    }
}
