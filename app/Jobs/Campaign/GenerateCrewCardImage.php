<?php

namespace App\Jobs\Campaign;

use App\Models\Campaign\CampaignCrewCard;
use App\Services\Campaign\CrewCardImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Regenerates a Campaign Crew Card catalog row's image in the background.
 * ShouldBeUnique collapses a burst of admin saves into a single render.
 * Mirrors App\Jobs\Campaign\GenerateLeaderCardImage.
 */
class GenerateCrewCardImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $crewCardId) {}

    public function uniqueId(): string
    {
        return "crew-card-{$this->crewCardId}";
    }

    public function handle(CrewCardImageGenerator $generator): void
    {
        $crewCard = CampaignCrewCard::find($this->crewCardId);
        if (! $crewCard) {
            return;
        }

        try {
            $generator->generate($crewCard);
        } catch (\Throwable $e) {
            // Best-effort: a render failure must not bubble up and block
            // whatever admin request triggered this dispatch.
            report($e);
        }
    }
}
