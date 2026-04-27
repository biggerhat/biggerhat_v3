<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\UnitSide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UnitSide */
class UnitSideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'side' => $this->side->value,
            'side_label' => $this->side->label(),
            'speed' => $this->speed,
            'defense' => $this->defense,
            'willpower' => $this->willpower,
            'armor' => $this->armor,
            'abilities' => AbilityResource::collection($this->whenLoaded('abilities')),
            'actions' => ActionResource::collection($this->whenLoaded('actions')),
        ];
    }
}
