<?php

namespace App\Observers;

use App\Jobs\Campaign\GenerateLeaderCardImage;
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
        'abilities', 'linked_crew_upgrades', 'linked_totems',
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
     * Card-image regeneration (Campaign Leaders/Totems only, pg 31/52) — the
     * single hook every mutation path funnels through (Leader Builder,
     * Advance Leader, direct Arsenal Sheet advancement logging/removal, Totem
     * creation), so none of those call sites need to remember to trigger it
     * themselves. Generic homebrew Custom Card Creator characters are
     * unaffected — they stay client-side-render-only.
     *
     * Dedicated `created`/`updated` hooks rather than the combined `saved`
     * event: Eloquent only populates change-tracking (`wasChanged()`) on
     * updates, not inserts, and `wasRecentlyCreated` stays true for the rest
     * of that model instance's lifetime once set — neither reliably tells
     * "was this specific save a create or an update" on its own.
     */
    public function created(CustomCharacter $character): void
    {
        if ($character->is_campaign_leader || $character->is_campaign_totem) {
            GenerateLeaderCardImage::dispatch($character->id);
        }
    }

    public function updated(CustomCharacter $character): void
    {
        if (! $character->is_campaign_leader && ! $character->is_campaign_totem) {
            return;
        }

        if (! $character->wasChanged(self::CARD_RENDER_COLUMNS)) {
            return;
        }

        GenerateLeaderCardImage::dispatch($character->id);
    }
}
