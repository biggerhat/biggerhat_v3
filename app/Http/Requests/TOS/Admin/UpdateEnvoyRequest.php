<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\EnvoyRestrictionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnvoyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_envoy') ?? false;
    }

    public function rules(): array
    {
        return [
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'name' => ['required', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'restriction' => ['required', 'string', Rule::enum(EnvoyRestrictionEnum::class)],
            'body' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ability_ids' => ['nullable', 'array'],
            'ability_ids.*' => ['integer', 'exists:tos_abilities,id'],
        ];
    }
}
