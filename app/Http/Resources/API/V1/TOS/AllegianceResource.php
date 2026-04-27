<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\Allegiance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Allegiance */
class AllegianceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_syndicate' => (bool) $this->is_syndicate,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'color_slug' => $this->color_slug,
            'sort_order' => $this->sort_order,
        ];
    }
}
