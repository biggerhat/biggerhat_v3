<?php

namespace App\Http\Requests\Collection;

use App\Enums\ModelStageEnum;
use App\Models\CollectionModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionUpdateModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('collectionModel');

        return $model instanceof CollectionModel && $model->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'stage' => ['nullable', Rule::enum(ModelStageEnum::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
