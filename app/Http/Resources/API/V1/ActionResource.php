<?php

namespace App\Http\Resources\API\V1;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\ResistanceTypeEnum;
use App\Models\Action;
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
        $type = ActionTypeEnum::tryFrom($this->type);
        $rangeType = ActionRangeTypeEnum::tryFrom($this->range_type ?? '');
        $resistedBy = ResistanceTypeEnum::tryFrom($this->resisted_by ?? '');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'type_label' => $type?->label(),
            'is_signature' => $this->is_signature,
            'costs_stone' => $this->costs_stone,
            'range' => $this->range,
            'range_type' => $this->range_type,
            'range_type_label' => $rangeType?->label(),
            'stat' => $this->stat,
            'stat_suits' => $this->stat_suits,
            'stat_modifier' => $this->stat_modifier,
            'resisted_by' => $this->resisted_by,
            'resisted_by_label' => $resistedBy?->label(),
            'target_number' => $this->target_number,
            'target_suits' => $this->target_suits,
            'description' => $this->description,
            'damage' => $this->damage,
            'triggers' => TriggerResource::collection($this->whenLoaded('triggers')),
        ];
    }
}
