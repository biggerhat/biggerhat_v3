<?php

namespace App\Services\Campaign;

use App\Models\CustomCharacter;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

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
        ]);
    }

    private function capture(string $url, string $selector): string
    {
        $browsershot = Browsershot::url($url)
            ->noSandbox()
            ->select($selector)
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

    /** Side-by-side merge — same GD approach as CreateCombinationImages, PNG instead of JPEG. */
    private function mergeSideBySide(string $frontPath, string $backPath, string $outPath): void
    {
        [$fw, $fh] = getimagesize($frontPath);
        [$bw, $bh] = getimagesize($backPath);

        $canvas = imagecreatetruecolor($fw + $bw, max($fh, $bh));
        imagecopy($canvas, imagecreatefrompng($frontPath), 0, 0, 0, 0, $fw, $fh);
        imagecopy($canvas, imagecreatefrompng($backPath), $fw, 0, 0, 0, $bw, $bh);
        imagepng($canvas, $outPath);
        imagedestroy($canvas);
    }
}
