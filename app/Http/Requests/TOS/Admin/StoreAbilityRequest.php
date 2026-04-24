<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\UsageLimitEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbilityRequest extends FormRequest
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
            'allegiance_id' => ['nullable', 'integer', 'exists:tos_allegiances,id'],
            'usage_limit' => ['nullable', 'string', Rule::enum(UsageLimitEnum::class)],
        ];
    }
}
