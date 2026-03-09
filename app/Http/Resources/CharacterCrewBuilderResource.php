<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Character */
class CharacterCrewBuilderResource extends JsonResource
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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'faction' => $this->faction,
            'second_faction' => $this->second_faction,
            'station' => $this->station,
            'cost' => $this->cost,
            'count' => $this->count,
            'has_totem_id' => $this->has_totem_id,
            'keywords' => $this->whenLoaded('keywords', fn () => $this->keywords->map(fn ($k) => [
                'id' => $k->id,
                'name' => $k->name,
                'slug' => $k->slug,
            ])),
            'characteristics' => $this->whenLoaded('characteristics', fn () => $this->characteristics->pluck('name')->map(fn ($name) => strtolower($name))),
            'crew_upgrades' => $this->whenLoaded('crewUpgrades', fn () => $this->crewUpgrades->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'slug' => $u->slug,
                'front_image' => $u->front_image,
                'back_image' => $u->back_image,
                'keywords' => $u->relationLoaded('keywords') ? $u->keywords->map(fn ($k) => [
                    'id' => $k->id,
                    'name' => $k->name,
                    'slug' => $k->slug,
                ]) : [],
            ])),
            'totem_slug' => $this->when($this->relationLoaded('totem'), fn () => $this->totem?->slug),
            /** @phpstan-ignore-next-line */
            'miniatures' => $this->whenLoaded('miniatures', fn () => $this->miniatures->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'title' => $m->title,
                'display_name' => $m->display_name,
                'slug' => $m->slug,
                'version' => $m->version,
                'front_image' => $m->front_image,
                'back_image' => $m->back_image,
            ])),
        ];
    }
}
