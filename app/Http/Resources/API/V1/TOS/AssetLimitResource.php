<?php

namespace App\Http\Resources\API\V1\TOS;

use App\Models\TOS\AssetLimit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetLimit */
class AssetLimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'limit_type' => $this->limit_type->value,
            'limit_type_label' => $this->limit_type->label(),
            'parameter_type' => $this->parameter_type?->value,
            'parameter_type_label' => $this->parameter_type?->label(),
            'parameter_value' => $this->parameter_value,
            'parameter_unit_id' => $this->parameter_unit_id,
            'parameter_allegiance_id' => $this->parameter_allegiance_id,
        ];
    }
}
