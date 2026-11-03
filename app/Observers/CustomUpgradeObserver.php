<?php

namespace App\Observers;

use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\CustomUpgrade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomUpgradeObserver
{
    /**
     * Columns that change the rendered card (everything UpgradeFrontFace / UpgradeBackFace read).
     *
     * @var array<int, string>
     */
    private const CARD_RENDER_COLUMNS = [
        'name', 'domain', 'faction', 'type', 'limitations', 'plentiful', 'master_name', 'keyword_name',
        'content_blocks', 'back_tokens', 'back_markers',
    ];

    public function created(CustomUpgrade $upgrade): void
    {
        $upgrade->queueCardImage();
    }

    /** Removes the saved front/back/combined PNGs along with the (soft-)deleted card. */
    public function deleted(CustomUpgrade $upgrade): void
    {
        Storage::disk('public')->deleteDirectory("custom-upgrades/{$upgrade->id}");
    }

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
        if ($upgrade->wasChanged(self::CARD_RENDER_COLUMNS)) {
            $upgrade->queueCardImage();
        }

        if ($upgrade->is_campaign_crew_card && $upgrade->campaign_crew_id && $upgrade->wasChanged('content_blocks')) {
            GenerateCombinedCrewCardImage::dispatch($upgrade->campaign_crew_id)->afterCommit();
        }
    }
}
