<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
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
            'scrip_cost' => $this->scrip_cost,
            'disable_count' => $this->disable_count,
            'scrap_count' => $this->scrap_count,
            'body' => $this->body,
            'image_path' => $this->image_path,
            'sort_order' => $this->sort_order,
            'allegiances' => AllegianceResource::collection($this->whenLoaded('allegiances')),
            'abilities' => AbilityResource::collection($this->whenLoaded('abilities')),
            'actions' => ActionResource::collection($this->whenLoaded('actions')),
            'limits' => AssetLimitResource::collection($this->whenLoaded('limits')),
        ];
    }
}
