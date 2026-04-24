<?php

namespace App\Http\Requests\TOS\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAbilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_ability') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'is_general' => ['required', 'boolean'],
        ];
    }
}
