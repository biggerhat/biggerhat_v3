<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Ability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ability */
class AbilityResource extends JsonResource
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
            'is_general' => (bool) $this->is_general,
            'usage_limit' => $this->usage_limit?->value,
            'usage_limit_label' => $this->usage_limit?->label(),
            'allegiance_id' => $this->allegiance_id,
            'allegiance' => $this->whenLoaded('allegiance', fn () => $this->allegiance ? new AllegianceResource($this->allegiance) : null),
        ];
    }
}
