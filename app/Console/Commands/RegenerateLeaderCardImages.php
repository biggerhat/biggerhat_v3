<?php

namespace App\Console\Commands;

use App\Jobs\Campaign\GenerateLeaderCardImage;
use App\Models\CustomCharacter;
use Illuminate\Console\Command;

/**
 * One-time backfill for Campaign Leader/Totem card images generated before a
 * fix to the capture pipeline (missing Browsershot viewport size, and the
 * capture page rendering with the full site layout instead of bare) — those
 * rows already have front_image/back_image/combination_image set, so the
 * CustomCharacterObserver's regenerate-on-save hook never re-fires for them
 * on its own. Re-dispatches generation for every currently active
 * Leader/Totem so already-existing rows pick up the fix too.
 */
class RegenerateLeaderCardImages extends Command
{
    protected $signature = 'app:regenerate-leader-card-images';

    protected $description = 'Re-queues card image generation for every current Campaign Leader/Totem and every homebrew character in a campaign arsenal.';

    public function handle(): int
    {
        $ids = CustomCharacter::query()
            ->where(fn ($q) => $q
                ->where(fn ($leaders) => $leaders->where('current', true)->where(fn ($l) => $l->where('is_campaign_leader', true)->orWhere('is_campaign_totem', true)))
                // Homebrew characters hired into a campaign arsenal are shown from the same saved image.
                ->orWhereHas('arsenalModels'))
            ->pluck('id');

        foreach ($ids as $id) {
            GenerateLeaderCardImage::dispatch($id);
        }

        $this->info("Queued card image regeneration for {$ids->count()} card(s).");

        return self::SUCCESS;
    }
}
