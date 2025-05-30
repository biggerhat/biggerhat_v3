<?php

namespace App\Observers;

use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Support\Str;

class CharacterObserver
{
    /**
     * Handle the Character 'creating' event.
     */
    public function creating(Character $character): void
    {
        $character->display_name = $character->name;
        if ($character->title) {
            $character->display_name .= ", {$character->title}";
        }

        $character->slug = Str::slug($character->display_name);
    }

    /**
     * Handle the Character "created" event.
     */
    public function created(Character $character): void {}

    /**
     * Handle the Character 'updating' event.
     */
    public function updating(Character $character): void
    {
        if ($character->isDirty('name') || $character->isDirty('title')) {
            $character->display_name = $character->name;
            if ($character->title) {
                $character->display_name .= ", {$character->title}";
            }
            $character->slug = Str::slug($character->display_name);

            $character->miniatures()
                ->whereNull('title')
                ->whereNull('name')
                ->get()
                ->each(function (Miniature $miniature) use ($character) {
                    $miniature->updateQuietly([
                        'display_name' => $character->display_name,
                        'slug' => $character->slug,
                    ]);
                });
        }
    }

    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        //
    }

    /**
     * Handle the Character "deleted" event.
     */
    public function deleted(Character $character): void
    {
        //
    }

    /**
     * Handle the Character "restored" event.
     */
    public function restored(Character $character): void
    {
        //
    }

    /**
     * Handle the Character "force deleted" event.
     */
    public function forceDeleted(Character $character): void
    {
        //
    }
}
