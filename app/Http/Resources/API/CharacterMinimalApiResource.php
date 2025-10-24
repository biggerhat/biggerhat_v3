<?php

namespace App\Http\Resources\API;

use App\Enums\CardTypeEnum;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/** @mixin Character */
class CharacterMinimalApiResource extends JsonResource
{
    public function __construct(Character $resource)
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
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'faction' => $this->faction,
            'station' => $this->station,
            'front_image' => $this->whenLoaded('miniatures', fn () => Storage::disk('public')->url($this->miniatures->first()->front_image)),
            'back_image' => $this->whenLoaded('miniatures', fn () => Storage::disk('public')->url($this->miniatures->first()->back_image)),
            'combination_image' => $this->whenLoaded('miniatures', fn () => Storage::disk('public')->url($this->miniatures->first()->combination_image)),
            'view' => route('characters.view', [$this->slug, $this->miniatures?->first()->id,$this->miniatures?->first()->slug]),
        ];
    }
}
