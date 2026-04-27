<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Action */
class ActionResource extends JsonResource
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
            'body' => $this->body,
            'av' => $this->av,
            'av_target' => $this->av_target,
            'tn' => $this->tn,
            'range' => $this->range,
            'strength' => $this->strength,
            'is_piercing' => (bool) $this->is_piercing,
            'is_accurate' => (bool) $this->is_accurate,
            'is_area' => (bool) $this->is_area,
            'usage_limit' => $this->usage_limit?->value,
            'usage_limit_label' => $this->usage_limit?->label(),
            'types' => $this->whenLoaded('typeLinks', fn () => $this->types->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()),
            'triggers' => TriggerResource::collection($this->whenLoaded('triggers')),
        ];
    }
}
