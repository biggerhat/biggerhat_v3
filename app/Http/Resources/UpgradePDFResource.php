<?php

namespace App\Http\Resources;

use App\Enums\CardTypeEnum;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Upgrade */
class UpgradePDFResource extends JsonResource
{
    public function __construct(Upgrade $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'card_type' => CardTypeEnum::Upgrade,
            'type' => $this->type->label(),
            'faction' => $this->faction,
            'front_image' => $this->front_image,
            'back_image' => $this->back_image,
            'combination_image' => $this->combination_image,
            'master' => $this->when($this->relationLoaded('master'), fn () => $this->master?->display_name),
            'count' => $this->plentiful,
        ];
    }
}
