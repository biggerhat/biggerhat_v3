<?php

namespace App\Services\Campaign;

use App\Events\CardImageGenerated;
use App\Models\Campaign\CampaignCrew;
use App\Services\Campaign\Concerns\CapturesCardImages;
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
    // This class keeps its own capture() (it waits on a descendant selector); the trait supplies merge + thumbnail.
    use CapturesCardImages;

    public function generate(CampaignCrew $crew): void
    {
        $dir = "campaign-crews/{$crew->id}";
        // CampaignCrew's route key is share_code (unguessable), not id —
        // matches the trust model this capture route relies on (same as
        // CustomCharacterController::capture's own share_code-based route).
        $url = route('tools.card_creator.capture_crew_card_combined', $crew->share_code);

        // Two element screenshots off one page load (same pattern as
        // LeaderCardImageGenerator's front/back capture) — front is the
        // starter effect, back is every currently-held Tier-4 borrow (T2-22).
        $front = $this->capture($url, '#card-crew-front');
        Storage::disk('public')->put("{$dir}/crew-card.png", $front);

        $back = $this->capture($url, '#card-crew-back');
        Storage::disk('public')->put("{$dir}/crew-card-back.png", $back);

        $disk = Storage::disk('public');
        $this->mergeSideBySide($disk->path("{$dir}/crew-card.png"), $disk->path("{$dir}/crew-card-back.png"), $disk->path("{$dir}/crew-card-combination.png"));
        $thumb = $this->makeThumbnail($disk->path("{$dir}/crew-card.png"), $disk->path("{$dir}/crew-card-thumb.webp"));

        $crew->update([
            'crew_card_front_image' => "{$dir}/crew-card.png",
            'crew_card_back_image' => "{$dir}/crew-card-back.png",
            'crew_card_combination_image' => "{$dir}/crew-card-combination.png",
            'crew_card_thumb_image' => $thumb ? "{$dir}/crew-card-thumb.webp" : null,
            'crew_card_generated_at' => now(),
        ]);

        if ($crew->user_id) {
            try {
                event(new CardImageGenerated($crew->user_id, CardImageGenerated::KIND_CREW_CARD, $crew->id, $crew->crew_card_generated_at?->toIso8601String()));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function capture(string $url, string $selector): string
    {
        $browsershot = Browsershot::url($url)
            ->noSandbox()
            ->select($selector)
            // CombinedCrewCardFace measures its own real rendered content
            // and settles on the smallest tarot-tiered box (up to a max of
            // 1150x1986) it actually fits in — the viewport just needs to
            // comfortably exceed the largest tier plus the capture page's
            // padding so Chrome never has to reflow the fixed-width card;
            // the element screenshot itself captures exactly whatever size
            // the face rendered at. waitForSelector blocks the screenshot
            // until that face's own measurement pass has finished (see
            // data-capture-ready in CombinedCrewCardFace.vue) — network
            // idle alone doesn't wait for this JS-driven layout settling.
            ->windowSize(1300, 2200)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            // Descendant combinator: data-capture-ready lives on
            // CombinedCrewCardFace's own root, one level inside the
            // #card-crew-front/#card-crew-back wrapper div $selector targets.
            ->waitForSelector("{$selector} [data-capture-ready=\"true\"]")
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
