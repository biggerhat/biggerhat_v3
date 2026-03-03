<?php

namespace App\Http\Resources\API;

use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Upgrade */
class CrewUpgradeApiResource extends JsonResource
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
            'type' => $this->type?->label(),
            'faction' => $this->faction,
            'front_image' => Storage::disk('public')->url($this->front_image),
            'back_image' => Storage::disk('public')->url($this->back_image),
            'combination_image' => Storage::disk('public')->url($this->combination_image),
            'masters' => $this->when($this->relationLoaded('masters'), function () use ($request) {
                return CharacterMinimalApiResource::collection($this->masters)->toArray($request);
            }),
            'view' => route('upgrades.view', [$this->slug]),
        ];
    }
}
