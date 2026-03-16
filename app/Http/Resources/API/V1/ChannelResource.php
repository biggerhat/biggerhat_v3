<?php

namespace App\Http\Resources\API\V1;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Channel */
class ChannelResource extends JsonResource
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
            'image_url' => $this->image_url,
            'transmissions_count' => $this->whenCounted('transmissions'),
            'transmissions' => TransmissionResource::collection($this->whenLoaded('transmissions')),
        ];
    }
}
