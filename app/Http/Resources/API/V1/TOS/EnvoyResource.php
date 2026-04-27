<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Envoy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Envoy */
class EnvoyResource extends JsonResource
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
            'keyword' => $this->keyword,
            'restriction' => $this->restriction->value,
            'restriction_label' => $this->restriction->label(),
            'body' => $this->body,
            'image_path' => $this->image_path,
            'sort_order' => $this->sort_order,
            'allegiance_id' => $this->allegiance_id,
            'allegiance' => $this->whenLoaded('allegiance', fn () => $this->allegiance ? new AllegianceResource($this->allegiance) : null),
            'abilities' => AbilityResource::collection($this->whenLoaded('abilities')),
        ];
    }
}
