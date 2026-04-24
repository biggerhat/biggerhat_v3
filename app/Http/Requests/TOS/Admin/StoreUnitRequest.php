<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\UnitSideEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_unit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'scrip' => ['required', 'integer'],
            'tactics' => ['nullable', 'string', 'max:8'],
            'description' => ['nullable', 'string'],
            'lore_text' => ['nullable', 'string'],
            'combined_arms_child_id' => ['nullable', 'integer', 'exists:tos_units,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // `restriction` flags a unit as "Neutral (Earth)" or "Neutral
            // (Malifaux)" — hireable by any Allegiance of the matching type
            // even without an explicit pivot attachment. Requires at least
            // one of allegiance_ids OR restriction to be present.
            'restriction' => ['nullable', 'string', Rule::enum(AllegianceTypeEnum::class), 'required_without:allegiance_ids'],
            'allegiance_ids' => ['nullable', 'array', 'required_without:restriction'],
            'allegiance_ids.*' => ['integer', 'exists:tos_allegiances,id'],

            // Both sides are required — every Unit Card has a Standard AND a Glory side.
            'sides' => ['required', 'array', 'size:2'],
            'sides.*.side' => ['required', 'string', Rule::enum(UnitSideEnum::class)],
            'sides.*.speed' => ['required', 'integer', 'min:0', 'max:255'],
            'sides.*.defense' => ['required', 'integer', 'min:0', 'max:255'],
            'sides.*.willpower' => ['required', 'integer', 'min:0', 'max:255'],
            'sides.*.armor' => ['required', 'integer', 'min:0', 'max:255'],
            'sides.*.ability_ids' => ['nullable', 'array'],
            'sides.*.ability_ids.*' => ['integer', 'exists:tos_abilities,id'],
            'sides.*.action_ids' => ['nullable', 'array'],
            'sides.*.action_ids.*' => ['integer', 'exists:tos_actions,id'],

            'special_rules' => ['nullable', 'array'],
            'special_rules.*.special_unit_rule_id' => ['required_with:special_rules', 'integer', 'exists:tos_special_unit_rules,id'],
            'special_rules.*.parameters' => ['nullable', 'array'],
        ];
    }
}
