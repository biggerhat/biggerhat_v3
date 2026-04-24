<?php

namespace App\Http\Requests\TOS\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecialUnitRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_special_unit_rule') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
