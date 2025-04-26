<?php

namespace App\Console\Commands;

use App\Models\Miniature;
use App\Models\Upgrade;
use Illuminate\Console\Command;
use Storage;
use Str;

class CreateCombinationImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-combination-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will create combination images where none exist.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $miniatures = Miniature::whereNotNull('front_image')
            ->whereNotNull('back_image')
            ->whereNull('combination_image')
            ->get();

        $upgrades = Upgrade::whereNotNull('front_image')
            ->whereNotNull('back_image')
            ->whereNull('combination_image')
            ->get();

        foreach ($miniatures as $miniature) {
            [$widthFront, $heightFront] = getimagesize(Storage::disk('public')->path($miniature->front_image));
            [$widthBack, $heightBack] = getimagesize(Storage::disk('public')->path($miniature->back_image));
            $background = imagecreatetruecolor($widthFront + $widthBack, $heightFront);

            header('Content-Type: image/jpeg');
            $outputImage = $background;

            $frontUrl = imagecreatefromjpeg(Storage::disk('public')->path($miniature->front_image));
            $backUrl = imagecreatefromjpeg(Storage::disk('public')->path($miniature->back_image));

            imagecopymerge($outputImage, $frontUrl, 0, 0, 0, 0, $widthFront, $heightFront, 100);
            imagecopymerge($outputImage, $backUrl, $widthFront, 0, 0, 0, $widthBack, $heightBack, 100);

            $extension = 'jpg';
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_combo.%s', $miniature->character_id, $uuid, $extension);
            $filePath = "characters/{$miniature->character_id}/{$fileName}";

            $path = Storage::disk('public')->path('/');
            imagejpeg($outputImage, $path.$filePath);
            $miniature->update(['combination_image' => $filePath]);
            imagedestroy($outputImage);
        }

        foreach ($upgrades as $upgrade) {
            [$widthFront, $heightFront] = getimagesize(Storage::disk('public')->path($upgrade->front_image));
            [$widthBack, $heightBack] = getimagesize(Storage::disk('public')->path($upgrade->back_image));
            $background = imagecreatetruecolor($widthFront + $widthBack, $heightFront);

            header('Content-Type: image/jpeg');
            $outputImage = $background;

            $frontUrl = imagecreatefromjpeg(Storage::disk('public')->path($upgrade->front_image));
            $backUrl = imagecreatefromjpeg(Storage::disk('public')->path($upgrade->back_image));

            imagecopymerge($outputImage, $frontUrl, 0, 0, 0, 0, $widthFront, $heightFront, 100);
            imagecopymerge($outputImage, $backUrl, $widthFront, 0, 0, 0, $widthBack, $heightBack, 100);

            $extension = 'jpg';
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_combo.%s', $upgrade->slug, $uuid, $extension);
            $filePath = "upgrades/{$upgrade->slug}/{$fileName}";

            $path = Storage::disk('public')->path('/');
            imagejpeg($outputImage, $path.$filePath);
            $upgrade->update(['combination_image' => $filePath]);
            imagedestroy($outputImage);
        }
    }
}
