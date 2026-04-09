<?php

namespace App\Http\Resources;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Keyword */
class KeywordResource extends JsonResource
{
    public function __construct(Keyword $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $characters = $this->relationLoaded('characters') ? $this->characters : collect();
        $total = $characters->count();
        $nonMasters = $characters->reject(fn ($c) => $c->station === CharacterStationEnum::Master);

        // Faction distribution
        $factionCounts = $characters->groupBy(fn ($c) => $c->faction->value)->map->count();
        $factions = $factionCounts->map(fn ($count, $faction) => [
            'value' => $faction,
            'label' => FactionEnum::from($faction)->label(),
            'count' => $count,
            'percent' => $total > 0 ? round($count / $total * 100) : 0,
        ])->sortByDesc('count')->values()->all();

        // Pick a representative miniature image: prefer a master, fall back to any character
        $representativeImage = null;
        $masters = $this->relationLoaded('masters') ? $this->masters : collect();
        $imageSource = $masters->isNotEmpty() ? $masters->random() : ($characters->isNotEmpty() ? $characters->random() : null);
        if ($imageSource && $imageSource->relationLoaded('standardMiniatures') && $imageSource->standardMiniatures->isNotEmpty()) {
            $representativeImage = $imageSource->standardMiniatures->first()->front_image;
        }

        // Station breakdown
        $stations = [];
        $stationOrder = [
            CharacterStationEnum::Master,
            CharacterStationEnum::Minion,
            CharacterStationEnum::Peon,
        ];
        foreach ($stationOrder as $station) {
            $count = $characters->where('station', $station)->count();
            if ($count > 0) {
                $stations[] = ['label' => $station->label(), 'count' => $count];
            }
        }
        $uniqueCount = $characters->whereNull('station')->count();
        if ($uniqueCount > 0) {
            $stations[] = ['label' => 'Unique', 'count' => $uniqueCount];
        }

        // Masters with their upgrades for display
        $masterSummaries = $masters->map(function (Character $master) {
            $miniature = $master->relationLoaded('standardMiniatures') && $master->standardMiniatures->isNotEmpty()
                ? $master->standardMiniatures->first()
                : null;

            return [
                'name' => $master->display_name,
                'slug' => $master->slug,
                'faction' => $master->faction->value,
                'miniature' => $miniature ? [
                    'id' => $miniature->id,
                    'slug' => $miniature->slug,
                    'display_name' => $master->display_name,
                    'front_image' => $miniature->front_image,
                    'back_image' => $miniature->back_image,
                    'character_id' => $master->id,
                ] : null,
                'crew_upgrades' => $master->crewUpgrades->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'slug' => $u->slug,
                    'front_image' => $u->front_image,
                    'back_image' => $u->back_image,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'has_master' => $masters->isNotEmpty(),
            'master_summaries' => $masterSummaries,
            'characters_count' => $total,
            'miniatures_count' => (int) $characters->sum('count'),
            'packages_count' => $this->packages_count ?? 0,
            'image' => $representativeImage,
            'factions' => $factions,
            'stations' => $stations,
            'stats' => [
                'avg_cost' => $nonMasters->avg('cost') ? round($nonMasters->avg('cost'), 1) : null,
                'avg_health' => $nonMasters->avg('health') ? round($nonMasters->avg('health'), 1) : null,
                'avg_defense' => $nonMasters->avg('defense') ? round($nonMasters->avg('defense'), 1) : null,
                'avg_willpower' => $nonMasters->avg('willpower') ? round($nonMasters->avg('willpower'), 1) : null,
                'avg_speed' => $nonMasters->avg('speed') ? round($nonMasters->avg('speed'), 1) : null,
            ],
        ];
    }
}
