<?php

namespace App\Http\Resources\API\V1;

use App\Models\Strategy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Strategy */
class StrategyResource extends JsonResource
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
            'season' => $this->season->value,
            'season_label' => $this->season->label(),
            'suit' => $this->suit?->value,
            'suit_label' => $this->suit?->label(),
            'setup' => $this->setup,
            'rules' => $this->rules,
            'scoring' => $this->scoring,
            'additional_scoring' => $this->additional_scoring,
            'image' => $this->image,
        ];
    }
}
