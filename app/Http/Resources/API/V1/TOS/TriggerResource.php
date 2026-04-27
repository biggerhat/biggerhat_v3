<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Trigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Trigger */
class TriggerResource extends JsonResource
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
            'suits' => $this->suits,
            'timing' => $this->timing->value,
            'timing_label' => $this->timing->label(),
        ];
    }
}
