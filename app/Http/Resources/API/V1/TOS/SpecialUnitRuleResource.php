<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\SpecialUnitRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SpecialUnitRule */
class SpecialUnitRuleResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'parameters' => $this->whenPivotLoaded('tos_unit_special_rule', fn () => $this->pivot->parameters),
        ];
    }
}
