<?php

namespace App\Http\Resources;

use App\Enums\CardTypeEnum;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Character */
class CharacterPDFResource extends JsonResource
{
    public function __construct(Character $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $keywords = [];
        foreach ($this->keywords as $keyword) {
            $keywords[] = [
                'id' => $keyword->id,
                'name' => $keyword->name,
                'slug' => $keyword->slug,
            ];
        }

        $miniatures = [];
        foreach ($this->standardMiniatures as $miniature) {
            $miniatures[] = [
                'id' => $miniature->id,
                'back_image' => $miniature->back_image,
                'front_image' => $miniature->front_image,
                'combination_image' => $miniature->combination_image,
                'display_name' => $miniature->display_name,
                'slug' => $miniature->slug,
            ];
        }

        $crewUpgrades = [];
        foreach ($this->crewUpgrades as $upgrade) {
            $crewUpgrades[] = $upgrade->slug;
        }

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'card_type' => CardTypeEnum::Miniature,
            'slug' => $this->slug,
            'faction' => $this->faction,
            'station' => $this->station,
            'keywords' => $keywords,
            'crew_upgrades' => $crewUpgrades,
            'totem_name' => $this->when($this->relationLoaded('totem'), fn () => $this->totem?->slug),
            'standard_miniatures' => $miniatures,
            'count' => $this->count,
            'cost' => $this->cost,
        ];
    }
}
