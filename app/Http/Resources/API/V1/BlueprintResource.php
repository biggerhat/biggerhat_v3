<?php

namespace App\Http\Resources\API\V1;

use App\Models\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Blueprint */
class BlueprintResource extends JsonResource
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
            'sculpt_version' => $this->sculpt_version->value,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'published_at' => $this->published_at?->toDateString(),
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
            'miniatures' => MiniatureResource::collection($this->whenLoaded('miniatures')),
            'packages' => PackageResource::collection($this->whenLoaded('packages')),
        ];
    }
}
