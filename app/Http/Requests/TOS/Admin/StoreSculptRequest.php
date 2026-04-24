<?php

namespace App\Http\Requests\TOS\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSculptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_sculpt') ?? false;
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'exists:tos_units,id'],
            'name' => ['required', 'string', 'max:255'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'release_date' => ['nullable', 'date'],
            'box_reference' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
