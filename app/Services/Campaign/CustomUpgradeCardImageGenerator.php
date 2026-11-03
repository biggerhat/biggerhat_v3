<?php

namespace App\Services\Campaign;

use App\Events\CardImageGenerated;
use App\Models\CustomUpgrade;
use App\Services\Campaign\Concerns\CapturesCardImages;
use Illuminate\Support\Facades\Storage;

/**
 * Saved front / back / combined images for a custom upgrade or crew card — same Browsershot-against-a-live-capture-
 * page approach as LeaderCardImageGenerator, so the PNG is the real UpgradeFrontFace/UpgradeBackFace, not a copy.
 */
class CustomUpgradeCardImageGenerator
{
    use CapturesCardImages;

    public function generate(CustomUpgrade $upgrade): void
    {
        $dir = "custom-upgrades/{$upgrade->id}";
        $url = route('tools.card_creator.upgrades.capture', $upgrade->share_code);

        Storage::disk('public')->put("{$dir}/front.png", $this->capture($url, '#card-front'));
        Storage::disk('public')->put("{$dir}/back.png", $this->capture($url, '#card-back'));

        $this->mergeSideBySide(
            Storage::disk('public')->path("{$dir}/front.png"),
            Storage::disk('public')->path("{$dir}/back.png"),
            Storage::disk('public')->path("{$dir}/combination.png"),
        );

        $upgrade->update([
            'front_image' => "{$dir}/front.png",
            'back_image' => "{$dir}/back.png",
            'combination_image' => "{$dir}/combination.png",
            'card_image_generated_at' => now(),
        ]);

        $this->announce(CardImageGenerated::KIND_CUSTOM_UPGRADE, $upgrade->id, $upgrade->user_id, $upgrade->card_image_generated_at);
    }
}
