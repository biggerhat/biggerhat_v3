<?php

namespace App\Http\Resources\API\V1;

use App\Models\Lore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lore */
class LoreResource extends JsonResource
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
            'file' => $this->file,
            'media' => LoreMediaResource::collection($this->whenLoaded('media')),
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
        ];
    }
}
