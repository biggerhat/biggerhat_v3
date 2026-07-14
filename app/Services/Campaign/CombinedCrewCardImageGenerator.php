<?php

namespace App\Services\Campaign;

use App\Models\Campaign\CampaignCrew;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

/**
 * Renders a crew's single combined Crew Card image (starter effect + every
 * held Tier-4 borrow, pg 15-16 / 32 / 54) via headless Chrome — same
 * Browsershot-against-a-live-Inertia-capture-page approach as
 * CrewCardImageGenerator / LeaderCardImageGenerator, so the generated image
 * is pixel-identical to the live preview.
 */
class CombinedCrewCardImageGenerator
{
    public function generate(CampaignCrew $crew): void
    {
        $dir = "campaign-crews/{$crew->id}";
        // CampaignCrew's route key is share_code (unguessable), not id —
        // matches the trust model this capture route relies on (same as
        // CustomCharacterController::capture's own share_code-based route).
        $url = route('tools.card_creator.capture_crew_card_combined', $crew->share_code);

        $front = $this->capture($url, '#card-crew');
        Storage::disk('public')->put("{$dir}/crew-card.png", $front);

        $crew->update(['crew_card_front_image' => "{$dir}/crew-card.png"]);
    }

    private function capture(string $url, string $selector): string
    {
        $browsershot = Browsershot::url($url)
            ->noSandbox()
            ->select($selector)
            // Same fixed 550x950 capture canvas as CrewCardImageGenerator.
            ->windowSize(750, 1100)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            ->timeout(60);

        if ($node = config('services.browsershot.node_binary')) {
            $browsershot->setNodeBinary($node);
        }
        if ($npm = config('services.browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npm);
        }
        if ($chrome = config('services.browsershot.chrome_path')) {
            $browsershot->setChromePath($chrome);
        }

        return $browsershot->screenshot();
    }
}
