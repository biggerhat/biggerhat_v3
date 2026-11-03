<?php

namespace App\Services\Campaign;

use App\Events\CardImageGenerated;
use App\Models\CustomCharacter;
use App\Services\Campaign\Concerns\CapturesCardImages;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a Campaign Leader/Totem's card to real PNGs via headless Chrome
 * (Browsershot) and saves them the same way Miniature/Upgrade store their
 * front/back/combination art (App\Console\Commands\CreateCombinationImages).
 *
 * Unlike App\Services\BonanzaDeckPdfGenerator (which renders a hand-written
 * Blade template), this navigates Browsershot to a live, minimal Inertia page
 * (CustomCharacterController::capture) that mounts the *real*
 * CardFrontFace/CardBackFace Vue components — the generated image is
 * pixel-identical to the live card preview because it is that component, with
 * no CSS duplicated/kept in sync by hand.
 */
class LeaderCardImageGenerator
{
    use CapturesCardImages;

    /**
     * Capture front + back, merge them into a combination image, and save all
     * three onto the character. Directory mirrors Miniature's
     * `characters/{character_id}/...` convention.
     */
    public function generate(CustomCharacter $character): void
    {
        $dir = "custom-characters/{$character->id}";
        $url = route('tools.card_creator.capture', $character->share_code);

        $front = $this->capture($url, '#card-front');
        Storage::disk('public')->put("{$dir}/front.png", $front);

        $back = $this->capture($url, '#card-back');
        Storage::disk('public')->put("{$dir}/back.png", $back);

        $this->mergeSideBySide(
            Storage::disk('public')->path("{$dir}/front.png"),
            Storage::disk('public')->path("{$dir}/back.png"),
            Storage::disk('public')->path("{$dir}/combination.png"),
        );

        $character->update([
            'front_image' => "{$dir}/front.png",
            'back_image' => "{$dir}/back.png",
            'combination_image' => "{$dir}/combination.png",
            // The one reliable "render actually finished" signal for the
            // Arsenal Sheet's regeneration poll — see the column's migration.
            'card_image_generated_at' => now(),
        ]);

        $this->announce(CardImageGenerated::KIND_CUSTOM_CHARACTER, $character->id, $character->user_id, $character->card_image_generated_at);
    }
}
