<?php

namespace App\Support\Game;

use App\Enums\GameFormatEnum;
use App\Models\Game;
use App\Models\Upgrade;
use App\Support\Campaign\AftermathCatalog;
use Illuminate\Support\Collection;

/**
 * What a player may attach to a model mid-game, shared by the play screen and the tabletop board so both offer the same lists.
 */
class CrewUpgradeCatalog
{
    /**
     * Upgrade catalog offered by the in-play "attach upgrade" editor. Campaign games swap the standard catalog for the full campaign
     * Equipment catalog (pg 21-29): mid-game, self-reported "attach anything on the table" freedom, same as the standard tracker's own
     * attach-any-upgrade flow.
     *
     * @return Collection<int, mixed>
     */
    public static function equipment(Game $game): Collection
    {
        if ($game->format !== GameFormatEnum::Campaign) {
            return Upgrade::standard()->forCharacters()->orderBy('name')
                ->get(['id', 'name', 'slug', 'front_image', 'back_image', 'type', 'plentiful', 'power_bar_count', 'description'])
                ->map(fn (Upgrade $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'slug' => $u->slug,
                    'front_image' => $u->front_image,
                    'back_image' => $u->back_image,
                    'type' => $u->type,
                    'plentiful' => $u->plentiful,
                    'power_bar_count' => $u->power_bar_count,
                    'description' => $u->description,
                ])
                ->values();
        }

        return collect(AftermathCatalog::equipmentCatalogForAttachment());
    }

    /**
     * The full Injury catalog (pg 34-36), offered as a mid-game attach option alongside equipment. Campaign games only; empty otherwise.
     *
     * @return Collection<int, mixed>
     */
    public static function injuries(Game $game): Collection
    {
        if ($game->format !== GameFormatEnum::Campaign) {
            return collect();
        }

        return collect(AftermathCatalog::injuryCatalogForAttachment());
    }
}
