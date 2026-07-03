<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvancementAbilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'is_joker' => ['required', 'boolean'],
            'is_always_available' => ['required', 'boolean'],
            'talent_name' => ['required', 'string', 'max:255'],
            'effect_text' => ['required', 'string'],
            'ability_id' => ['nullable', 'integer', 'exists:abilities,id'],
            // Bespoke ability shape — only meaningful when ability_id is null.
            'suits' => ['nullable', 'string', 'max:255'],
            'defensive_ability_type' => ['nullable', 'string', Rule::enum(DefensiveAbilityTypeEnum::class)],
        ];
    }
}
