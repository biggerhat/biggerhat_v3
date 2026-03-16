<?php

namespace App\Http\Resources\API\V1;

use App\Models\Transmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transmission */
class TransmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'url' => $this->url,
            'transmission_type' => $this->transmission_type->value,
            'transmission_type_label' => $this->transmission_type->label(),
            'content_type' => $this->content_type->value,
            'content_type_label' => $this->content_type->label(),
            'factions' => $this->factions,
            'release_date' => $this->release_date?->toDateString(),
            'channel' => new ChannelResource($this->whenLoaded('channel')),
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
            'keywords' => KeywordResource::collection($this->whenLoaded('keywords')),
        ];
    }
}
