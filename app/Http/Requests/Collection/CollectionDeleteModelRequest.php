<?php

namespace App\Http\Requests\Collection;

use App\Models\CollectionModel;
use Illuminate\Foundation\Http\FormRequest;

class CollectionDeleteModelRequest extends FormRequest
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
        return [];
    }
}
