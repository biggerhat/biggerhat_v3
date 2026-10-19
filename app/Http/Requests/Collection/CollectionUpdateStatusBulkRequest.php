<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

class CollectionUpdateStatusBulkRequest extends FormRequest
{
    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'miniature_ids' => 'required|array|min:1',
            'miniature_ids.*' => 'integer|exists:miniatures,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',

        ];
    }
}
