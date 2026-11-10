<?php

namespace App\Http\Requests\TOS\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_operation') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('tos_operations', 'name')->ignore($this->route('operation'))],
            'source' => ['nullable', 'string', 'max:255'],
            'flavor' => ['nullable', 'string'],
            'additional_setup' => ['nullable', 'string'],
            'special_rules' => ['nullable', 'string'],
            'victory_conditions' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
