<?php

namespace App\Services\Campaign;

use App\Models\Campaign\CampaignCrewCard;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

/**
 * Renders a Campaign Crew Card catalog row to a real PNG via headless Chrome
 * (Browsershot), same approach as LeaderCardImageGenerator — navigates to a
 * live, minimal Inertia page (CrewCardCaptureController::show) that mounts
 * the *real* CrewCardFace Vue component, so the generated image is
 * pixel-identical to the live preview.
 *
 * Unlike a Leader/Totem, a Crew Card has no separate front/back — it's one
 * face (name + rules text), so this saves a single `front_image`.
 */
class CrewCardImageGenerator
{
    public function generate(CampaignCrewCard $crewCard): void
    {
        $dir = "campaign-crew-cards/{$crewCard->id}";
        $url = route('tools.card_creator.capture_crew_card', $crewCard->id);

        $front = $this->capture($url, '#card-crew');
        Storage::disk('public')->put("{$dir}/front.png", $front);

        $crewCard->update(['front_image' => "{$dir}/front.png"]);
    }

    private function capture(string $url, string $selector): string
    {
        $browsershot = Browsershot::url($url)
            ->noSandbox()
            ->select($selector)
            // Capture page lays the single 550x950 card div out with p-8
            // outer padding — 550 + 64 = 614px wide, 950 + 64 = 1014px tall.
            // Sized with margin above both minimums, same reasoning as
            // LeaderCardImageGenerator.
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
