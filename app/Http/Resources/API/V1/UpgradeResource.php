<?php

namespace App\Http\Resources\API\V1;

use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Upgrade */
class UpgradeResource extends JsonResource
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
            'domain' => $this->domain->value,
            'domain_label' => $this->domain->label(),
            'faction' => $this->faction?->value,
            'faction_label' => $this->faction?->label(),
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'limitations' => $this->limitations?->value,
            'limitations_label' => $this->limitations?->label(),
            'description' => $this->description,
            'plentiful' => $this->plentiful,
            'front_image' => $this->front_image ? Storage::disk('public')->url($this->front_image) : null,
            'back_image' => $this->back_image ? Storage::disk('public')->url($this->back_image) : null,
            'combination_image' => $this->combination_image ? Storage::disk('public')->url($this->combination_image) : null,
            'keywords' => KeywordResource::collection($this->whenLoaded('keywords')),
            'actions' => ActionResource::collection($this->whenLoaded('actions')),
            'abilities' => AbilityResource::collection($this->whenLoaded('abilities')),
            'triggers' => TriggerResource::collection($this->whenLoaded('triggers')),
            'markers' => MarkerResource::collection($this->whenLoaded('markers')),
            'tokens' => TokenResource::collection($this->whenLoaded('tokens')),
            'characters' => CharacterResource::collection($this->whenLoaded('characters')),
        ];
    }
}
