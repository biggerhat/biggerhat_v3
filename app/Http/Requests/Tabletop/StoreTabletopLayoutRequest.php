<?php

namespace App\Http\Requests\Tabletop;

use App\Http\Requests\Games\UpdateTerrainRequest;
use App\Support\Tabletop\TabletopAccess;
use App\Support\Tabletop\TabletopImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTabletopLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && TabletopAccess::allows($this->user());
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'table_image' => ['nullable', 'string', Rule::in(TabletopImages::tableKeys())],
            ...UpdateTerrainRequest::terrainRules(),
            // An empty table is not worth keeping.
            'terrain' => ['required', 'array', 'min:1', 'max:40'],
        ];
    }
}
