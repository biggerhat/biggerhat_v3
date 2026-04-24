<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllegianceCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_allegiance_card') ?? false;
    }

    public function rules(): array
    {
        return [
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(AllegianceTypeEnum::class)],
            'body' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ability_ids' => ['nullable', 'array'],
            'ability_ids.*' => ['integer', 'exists:tos_abilities,id'],
        ];
    }
}
