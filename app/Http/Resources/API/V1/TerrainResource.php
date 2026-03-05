<?php

namespace App\Http\Resources\API\V1;

use App\Models\Terrain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Terrain */
class TerrainResource extends JsonResource
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
            'markers' => MarkerResource::collection($this->whenLoaded('markers')),
        ];
    }
}
