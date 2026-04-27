<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\UnitSculpt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UnitSculpt */
class UnitSculptResource extends JsonResource
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
            'unit_id' => $this->unit_id,
            'front_image' => $this->front_image,
            'back_image' => $this->back_image,
            'combination_image' => $this->combination_image,
            'release_date' => $this->release_date?->toDateString(),
            'box_reference' => $this->box_reference,
            'sort_order' => $this->sort_order,
        ];
    }
}
