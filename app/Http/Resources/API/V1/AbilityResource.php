<?php

namespace App\Http\Resources\API\V1;

use App\Models\Ability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ability */
class AbilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_mode_type' => $this->game_mode_type->value,
            'game_mode_type_label' => $this->game_mode_type->label(),
            'name' => $this->name,
            'slug' => $this->slug,
            'suits' => $this->suits,
            'defensive_ability_type' => $this->defensive_ability_type,
            'costs_stone' => $this->costs_stone,
            'description' => $this->description,
        ];
    }
}
