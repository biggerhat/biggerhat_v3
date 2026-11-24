<?php

namespace App\Http\Requests\Collection;

use App\Enums\ModelStageEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionAddModelRequest extends FormRequest
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
            'miniature_id' => ['required', 'integer', 'exists:miniatures,id'],
            'stage' => ['nullable', Rule::enum(ModelStageEnum::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
