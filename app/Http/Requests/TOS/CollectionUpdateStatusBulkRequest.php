<?php

namespace App\Http\Requests\TOS;

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
            'unit_sculpt_ids' => 'required|array|min:1',
            'unit_sculpt_ids.*' => 'integer|exists:tos_unit_sculpts,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',

        ];
    }
}
