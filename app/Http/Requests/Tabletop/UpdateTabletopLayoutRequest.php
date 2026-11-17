<?php

namespace App\Http\Requests\Tabletop;

use App\Http\Requests\Games\UpdateTerrainRequest;
use App\Models\TabletopLayout;
use App\Support\Tabletop\TabletopAccess;
use App\Support\Tabletop\TabletopImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTabletopLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $layout = $this->route('layout');

        return $layout instanceof TabletopLayout
            && $this->user() !== null
            && TabletopAccess::allows($this->user())
            && $layout->user_id === $this->user()->id;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', Rule::in(['private', 'link', 'public'])],
            'table_image' => ['sometimes', 'nullable', 'string', Rule::in(TabletopImages::tableKeys())],
            // Saving the table as it is now over this layout.
            ...UpdateTerrainRequest::terrainRules(),
            'terrain' => ['sometimes', 'array', 'min:1', 'max:40'],
        ];
    }
}
