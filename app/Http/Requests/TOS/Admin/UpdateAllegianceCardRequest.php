<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllegianceCardRequest extends FormRequest
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
            'secondary_type' => ['nullable', 'string', Rule::enum(AllegianceTypeEnum::class), 'different:type'],
            'body' => ['nullable', 'string'],
            'primary_body' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'ability_ids' => ['nullable', 'array'],
            'ability_ids.*' => ['integer', 'exists:tos_abilities,id'],
            'action_ids' => ['nullable', 'array'],
            'action_ids.*' => ['integer', 'exists:tos_actions,id'],
            'trigger_ids' => ['nullable', 'array'],
            'trigger_ids.*' => ['integer', 'exists:tos_triggers,id'],

            'primary_ability_ids' => ['nullable', 'array'],
            'primary_ability_ids.*' => ['integer', 'exists:tos_abilities,id'],
            'primary_action_ids' => ['nullable', 'array'],
            'primary_action_ids.*' => ['integer', 'exists:tos_actions,id'],
            'primary_trigger_ids' => ['nullable', 'array'],
            'primary_trigger_ids.*' => ['integer', 'exists:tos_triggers,id'],
        ];
    }
}
