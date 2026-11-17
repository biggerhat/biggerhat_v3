<?php

namespace App\Http\Requests\Games;

use App\Support\Tabletop\TabletopImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTerrainRequest extends FormRequest
{
    public const TRAITS = ['blocking', 'climbable', 'impassable', 'concealing', 'dense', 'hazardous', 'destructible', 'severe'];

    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /**
     * The whole table layout, replaced in one go. Everything is in inches on the 36" table, so coordinates are bounded to it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $coord = ['numeric', 'between:0,36'];

        return [
            'terrain' => ['present', 'array', 'max:40'],
            'terrain.*.id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'terrain.*.name' => ['required', 'string', 'max:60'],
            // Which kind of terrain this is (a preset key), so it is drawn with that kind's texture.
            'terrain.*.kind' => ['nullable', 'string', Rule::in(array_keys(TabletopImages::TERRAIN_KINDS))],
            'terrain.*.traits' => ['present', 'array', 'max:8'],
            'terrain.*.traits.*' => ['string', Rule::in(self::TRAITS)],
            'terrain.*.surfaces' => ['required', 'array', 'min:1', 'max:6'],
            'terrain.*.surfaces.*.id' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'terrain.*.surfaces.*.top' => ['required', 'numeric', 'between:0.5,12'],
            'terrain.*.surfaces.*.shape.kind' => ['required', Rule::in(['rect', 'circle', 'poly'])],
            'terrain.*.surfaces.*.shape.x' => ['required_if:terrain.*.surfaces.*.shape.kind,rect', ...$coord],
            'terrain.*.surfaces.*.shape.y' => ['required_if:terrain.*.surfaces.*.shape.kind,rect', ...$coord],
            'terrain.*.surfaces.*.shape.w' => ['required_if:terrain.*.surfaces.*.shape.kind,rect', 'numeric', 'between:0.5,36'],
            'terrain.*.surfaces.*.shape.h' => ['required_if:terrain.*.surfaces.*.shape.kind,rect', 'numeric', 'between:0.5,36'],
            'terrain.*.surfaces.*.shape.cx' => ['required_if:terrain.*.surfaces.*.shape.kind,circle', ...$coord],
            'terrain.*.surfaces.*.shape.cy' => ['required_if:terrain.*.surfaces.*.shape.kind,circle', ...$coord],
            'terrain.*.surfaces.*.shape.r' => ['required_if:terrain.*.surfaces.*.shape.kind,circle', 'numeric', 'between:0.5,36'],
            'terrain.*.surfaces.*.shape.points' => ['required_if:terrain.*.surfaces.*.shape.kind,poly', 'array', 'min:3', 'max:16'],
            'terrain.*.surfaces.*.shape.points.*' => ['array', 'size:2'],
            'terrain.*.surfaces.*.shape.points.*.*' => $coord,
        ];
    }
}
