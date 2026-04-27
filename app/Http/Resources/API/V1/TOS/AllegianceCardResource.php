<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\AllegianceCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AllegianceCard */
class AllegianceCardResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'body' => $this->body,
            'image_path' => $this->image_path,
            'sort_order' => $this->sort_order,
            'allegiance_id' => $this->allegiance_id,
            'allegiance' => $this->whenLoaded('allegiance', fn () => $this->allegiance ? new AllegianceResource($this->allegiance) : null),
            'abilities' => AbilityResource::collection($this->whenLoaded('abilities')),
        ];
    }
}
