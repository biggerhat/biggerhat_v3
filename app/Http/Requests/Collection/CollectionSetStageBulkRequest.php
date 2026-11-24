<?php

namespace App\Http\Requests\Collection;

use App\Enums\ModelStageEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionSetStageBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'miniature_ids' => ['required', 'array', 'min:1', 'max:500'],
            'miniature_ids.*' => ['integer', 'exists:miniatures,id'],
            'stage' => ['required', Rule::enum(ModelStageEnum::class)],
        ];
    }
}
