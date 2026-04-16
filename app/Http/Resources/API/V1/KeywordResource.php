<?php

namespace App\Http\Resources\API\V1;

use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Keyword */
class KeywordResource extends JsonResource
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
            'description' => $this->description,
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
        ];
    }
}
