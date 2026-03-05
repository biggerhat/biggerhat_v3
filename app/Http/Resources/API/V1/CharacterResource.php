<?php

namespace App\Http\Resources\API\V1;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Character */
class CharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'nicknames' => $this->nicknames,
            'faction' => $this->faction->value,
            'faction_label' => $this->faction->label(),
            'second_faction' => $this->second_faction?->value,
            'second_faction_label' => $this->second_faction?->label(),
            'station' => $this->station?->value,
            'station_label' => $this->station?->label(),
            'cost' => $this->cost,
            'health' => $this->health,
            'size' => $this->size,
            'base' => $this->base->value,
            'base_label' => $this->base->label(),
            'defense' => $this->defense,
            'defense_suit' => $this->defense_suit?->value,
            'willpower' => $this->willpower,
            'willpower_suit' => $this->willpower_suit?->value,
            'speed' => $this->speed,
            'count' => $this->count,
            'summon_target_number' => $this->summon_target_number,
            'generates_stone' => $this->generates_stone,
            'is_unhirable' => $this->is_unhirable,
            'is_beta' => $this->is_beta,
            'miniatures' => MiniatureResource::collection($this->whenLoaded('miniatures')),
            'keywords' => KeywordResource::collection($this->whenLoaded('keywords')),
            'actions' => ActionResource::collection($this->whenLoaded('actions')),
            'abilities' => AbilityResource::collection($this->whenLoaded('abilities')),
            'markers' => MarkerResource::collection($this->whenLoaded('markers')),
            'tokens' => TokenResource::collection($this->whenLoaded('tokens')),
            'upgrades' => UpgradeResource::collection($this->whenLoaded('characterUpgrades')),
        ];
    }
}
