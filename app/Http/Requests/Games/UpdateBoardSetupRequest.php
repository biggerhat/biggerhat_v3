<?php

namespace App\Http\Requests\Games;

use App\Support\Tabletop\TabletopImages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /**
     * Either of the table's shared settings, which either player may change (the two of them agree it at the table): which of the
     * four ways round the deployment zones sit, or the surface the table is drawn with (null for the plain one).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $variant = ['integer', 'between:0,3'];

        if ($this->has('table_image')) {
            return [
                'deployment_variant' => ['sometimes', ...$variant],
                'table_image' => ['nullable', 'string', Rule::in(TabletopImages::tableKeys())],
            ];
        }

        return ['deployment_variant' => ['required', ...$variant]];
    }
}
