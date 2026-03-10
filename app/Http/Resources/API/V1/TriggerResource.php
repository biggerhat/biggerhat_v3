<?php

namespace App\Http\Resources\API\V1;

use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Trigger */
class TriggerResource extends JsonResource
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
            'suits' => $this->suits,
            'stone_cost' => $this->stone_cost,
            'description' => $this->description,
            'actions' => ActionResource::collection($this->whenLoaded('actions')),
        ];
    }
}
