<?php

namespace App\Http\Resources\API\V1;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Package */
class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'distributor_description' => $this->distributor_description,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'factions' => $this->factions,
            'is_preassembled' => $this->is_preassembled,
            'released_at' => $this->released_at?->toDateString(),
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
            'keywords' => KeywordResource::collection($this->whenLoaded('keywords')),
            'miniatures' => MiniatureResource::collection($this->whenLoaded('miniatures')),
            'blueprints' => BlueprintResource::collection($this->whenLoaded('blueprints')),
            'store_links' => $this->whenLoaded('storeLinks', fn () => $this->storeLinks->map(fn ($link) => [
                'store_name' => $link->store_name,
                'url' => $link->url,
            ])),
        ];
    }
}
