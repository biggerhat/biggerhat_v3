<?php

namespace App\Http\Resources\API\V1;

use App\Enums\BaseSizeEnum;
use App\Models\Marker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Marker */
class MarkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = BaseSizeEnum::tryFrom((int) $this->base);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'base' => $this->base,
            'base_label' => $base?->label(),
            'icon' => $this->icon,
            'terrains' => TerrainResource::collection($this->whenLoaded('terrains')),
        ];
    }
}
