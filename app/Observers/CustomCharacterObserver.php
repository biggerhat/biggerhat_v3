<?php

namespace App\Observers;

use App\Models\CustomCharacter;
use Illuminate\Support\Str;

class CustomCharacterObserver
{
    /**
     * Columns that actually affect the rendered card — CardFrontFace/
     * CardBackFace's full prop list (see LeaderCardImageGenerator).
     *
     * @var array<int, string>
     */
    private const CARD_RENDER_COLUMNS = [
        'name', 'title', 'faction', 'second_faction', 'station', 'cost',
        'health', 'defense', 'defense_suit', 'willpower', 'willpower_suit',
        'speed', 'size', 'base', 'keywords', 'characteristics', 'actions',
        'abilities', 'linked_crew_upgrades', 'linked_totems', 'card_art_id', 'card_art_x', 'card_art_y', 'card_art_zoom',
    ];

    public function creating(CustomCharacter $character): void
    {
        $character->display_name = $character->name;
        if ($character->title) {
            $character->display_name .= ", {$character->title}";
        }

        $base = Str::slug($character->display_name);
        $slug = $base;
        $i = 1;
        while (CustomCharacter::where('slug', $slug)->where('user_id', $character->user_id)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        $character->slug = $slug;
    }

    public function updating(CustomCharacter $character): void
    {
        if ($character->isDirty('name') || $character->isDirty('title')) {
            $character->display_name = $character->name;
            if ($character->title) {
                $character->display_name .= ", {$character->title}";
            }

            $base = Str::slug($character->display_name);
            $slug = $base;
            $i = 1;
            while (CustomCharacter::where('slug', $slug)->where('user_id', $character->user_id)->where('id', '!=', $character->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $character->slug = $slug;
        }
    }

    /**
     * Saved card image (front/back/combination PNGs): the single hook every mutation path funnels through (Leader
     * Builder, Advance Leader, Arsenal Sheet advancements, Totem creation, the Custom Card Creator editor), so no
     * call site needs to remember to trigger it. CustomCharacter::queueCardImage() decides how — immediately and
     * unthrottled for Campaign Leaders/Totems (pg 31/52), a short delayed, coalesced render for everyone else.
     *
     * Dedicated `created`/`updated` hooks rather than the combined `saved` event: Eloquent only populates
     * change-tracking (`wasChanged()`) on updates, not inserts, and `wasRecentlyCreated` stays true for the rest of
     * that model instance's lifetime once set — neither reliably tells "was this specific save a create or an
     * update" on its own.
     *
     * queueCardImage() uses afterCommit(): every campaign call site creates/updates its Leader or Totem inside
     * DB::transaction, and queue connections here run with after_commit=false, so a worker could otherwise dequeue
     * the job before the transaction commits, fail to find the row and silently no-op.
     */
    public function created(CustomCharacter $character): void
    {
        $character->queueCardImage();
    }

    public function updated(CustomCharacter $character): void
    {
        if ($character->wasChanged(self::CARD_RENDER_COLUMNS)) {
            $character->queueCardImage();
        }
    }
}
