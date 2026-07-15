<?php

namespace App\Console\Commands;

use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Console\Command;

/**
 * One-time backfill for combined Crew Card images generated before the
 * tarot-tiered sizing fix (previously a hard-fixed 550x950 canvas that
 * clipped content and shrank text on any crew holding more than a couple of
 * effects) — those crews already have crew_card_front_image set, so nothing
 * re-triggers generation for them on its own. Mirrors
 * App\Console\Commands\RegenerateLeaderCardImages.
 */
class RegenerateCrewCardImages extends Command
{
    protected $signature = 'app:regenerate-crew-card-images';

    protected $description = 'Re-queues combined crew card image generation for every crew with a starter effect.';

    public function handle(): int
    {
        $ids = CampaignCrew::query()
            ->whereNotNull('crew_card_effect_id')
            ->pluck('id');

        foreach ($ids as $id) {
            GenerateCombinedCrewCardImage::dispatch($id);
        }

        $this->info("Queued combined crew card image regeneration for {$ids->count()} crew(s).");

        return self::SUCCESS;
    }
}
