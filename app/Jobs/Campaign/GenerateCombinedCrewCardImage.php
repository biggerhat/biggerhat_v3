<?php

namespace App\Jobs\Campaign;

use App\Models\Campaign\CampaignCrew;
use App\Services\Campaign\CombinedCrewCardImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates a crew's combined Crew Card image in the background —
 * dispatched whenever the crew's held effect set changes (Starting Arsenal
 * picks a starter, a Tier-4 borrow is taken or undone). ShouldBeUnique
 * collapses a burst of rapid changes into a single render. Mirrors
 * App\Jobs\Campaign\GenerateCrewCardImage.
 */
class GenerateCombinedCrewCardImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $campaignCrewId) {}

    public function uniqueId(): string
    {
        return "combined-crew-card-{$this->campaignCrewId}";
    }

    public function handle(CombinedCrewCardImageGenerator $generator): void
    {
        $crew = CampaignCrew::find($this->campaignCrewId);
        if (! $crew) {
            return;
        }

        try {
            $generator->generate($crew);
        } catch (\Throwable $e) {
            // Best-effort: a render failure must not bubble up and block
            // whatever crew action triggered this dispatch.
            report($e);
        }
    }
}
