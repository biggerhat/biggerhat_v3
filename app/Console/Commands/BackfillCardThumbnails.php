<?php

namespace App\Console\Commands;

use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Services\Campaign\Concerns\CapturesCardImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the small gallery thumbnail for saved card images rendered before thumbnails existed. Only reads the PNGs
 * already on disk — no Chrome, so it is cheap to run on the whole table.
 */
class BackfillCardThumbnails extends Command
{
    use CapturesCardImages;

    protected $signature = 'app:backfill-card-thumbnails';

    protected $description = 'Creates gallery thumbnails for custom character and upgrade card images that do not have one yet.';

    public function handle(): int
    {
        $made = 0;

        foreach ([CustomCharacter::class, CustomUpgrade::class] as $model) {
            $model::query()->whereNotNull('front_image')->whereNull('thumb_image')->each(function ($card) use (&$made) {
                $disk = Storage::disk('public');
                $thumb = dirname($card->front_image).'/thumb.webp';

                if ($this->makeThumbnail($disk->path($card->front_image), $disk->path($thumb))) {
                    // Straight to the query builder: a thumbnail is not a content change, so no observers / re-render.
                    $card->newQuery()->whereKey($card->id)->update(['thumb_image' => $thumb]);
                    $made++;
                }
            });
        }

        $this->info("Created {$made} thumbnail(s).");

        return self::SUCCESS;
    }
}
