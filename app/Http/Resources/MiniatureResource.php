<?php

namespace App\Http\Resources;

use App\Models\Miniature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Miniature */
class MiniatureResource extends JsonResource
{
    public function __construct(Miniature $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'character_id' => $this->character_id,
            'character_name' => $this->when($this->relationLoaded('character'), fn () => $this->character->display_name),
            'character' => $this->when($this->relationLoaded('character'), fn () => $this->character),
            'front_image' => $this->front_image,
            'back_image' => $this->back_image,
            'combination_image' => $this->combination_image,
            'version' => $this->version,
        ];
    }
}
