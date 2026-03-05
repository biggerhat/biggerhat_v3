<?php

namespace App\Http\Resources\API\V1;

use App\Enums\SculptVersionEnum;
use App\Models\Miniature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Miniature */
class MiniatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = SculptVersionEnum::tryFrom($this->version);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'front_image' => $this->front_image ? Storage::disk('public')->url($this->front_image) : null,
            'back_image' => $this->back_image ? Storage::disk('public')->url($this->back_image) : null,
            'combination_image' => $this->combination_image ? Storage::disk('public')->url($this->combination_image) : null,
            'version' => $this->version,
            'version_label' => $version?->label(),
        ];
    }
}
