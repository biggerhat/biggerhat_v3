<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Unit */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'scrip' => $this->scrip,
            'tactics' => $this->tactics,
            'description' => $this->description,
            'lore_text' => $this->lore_text,
            'restriction' => $this->restriction?->value,
            'restriction_label' => $this->restriction?->label(),
            'combined_arms_child_id' => $this->combined_arms_child_id,
            'sort_order' => $this->sort_order,
            'sides' => UnitSideResource::collection($this->whenLoaded('sides')),
            'sculpts' => UnitSculptResource::collection($this->whenLoaded('sculpts')),
            'allegiances' => AllegianceResource::collection($this->whenLoaded('allegiances')),
            'special_unit_rules' => SpecialUnitRuleResource::collection($this->whenLoaded('specialUnitRules')),
        ];
    }
}
