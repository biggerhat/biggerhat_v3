<?php

namespace App\Http\Resources\API\V1;

use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Scheme */
class SchemeResource extends JsonResource
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
            'season' => $this->season->value,
            'season_label' => $this->season->label(),
            'selector' => $this->selector,
            'prerequisite' => $this->prerequisite,
            'reveal' => $this->reveal,
            'scoring' => $this->scoring,
            'additional' => $this->additional,
            'image' => $this->image,
        ];
    }
}
