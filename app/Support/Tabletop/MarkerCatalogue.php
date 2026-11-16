<?php

namespace App\Support\Tabletop;

use App\Enums\BaseSizeEnum;
use App\Models\Marker;

/** The markers a player can put on the tabletop: every row of the markers table, with its base size in millimetres. */
class MarkerCatalogue
{
    /**
     * @return list<array{id: int, name: string, base_mm: int}>
     */
    public static function options(): array
    {
        return Marker::query()->orderBy('name')->get(['id', 'name', 'base'])
            ->map(fn (Marker $m) => [
                'id' => $m->id,
                'name' => $m->name,
                // `base` is a plain string column; anything that is not a known size falls back to 30 mm, the usual one.
                'base_mm' => (BaseSizeEnum::tryFrom((int) $m->base) ?? BaseSizeEnum::ThirtyMM)->value,
            ])
            ->all();
    }
}
