<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Stratagem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stratagem */
class StratagemResource extends JsonResource
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
            'tactical_cost' => $this->tactical_cost,
            'effect' => $this->effect,
            'image_path' => $this->image_path,
            'sort_order' => $this->sort_order,
            'allegiance_id' => $this->allegiance_id,
            'allegiance_type' => $this->allegiance_type?->value,
            'allegiance_type_label' => $this->allegiance_type?->label(),
            'allegiance' => $this->whenLoaded('allegiance', fn () => $this->allegiance ? new AllegianceResource($this->allegiance) : null),
        ];
    }
}
