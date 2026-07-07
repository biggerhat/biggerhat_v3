<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use App\Enums\SuitEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the Attack Mod / Tactical Mod advancement tables —
 * identical column shape, only the physical table differs per controller.
 */
class StoreAttackTacticalAdvancementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'is_black_joker' => ['required', 'boolean'],
            'is_red_joker' => ['required', 'boolean'],
            'is_always_available' => ['required', 'boolean'],
            'modifier_type' => ['required', 'string', Rule::in(['trigger', 'skl_boost', 'signature'])],
            'name' => ['required', 'string', 'max:255'],
            'effect_text' => ['required', 'string'],
            'suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            // skl_from is the qualifying range's minimum (or exact value when
            // skl_from_max is left blank); skl_from_max, when set, must be >= it.
            'skl_from' => ['nullable', 'integer', 'min:0', 'max:10'],
            'skl_from_max' => ['nullable', 'integer', 'min:0', 'max:10', 'gte:skl_from'],
            'skl_to' => ['nullable', 'integer', 'min:0', 'max:10'],
            'trigger_id' => ['nullable', 'integer', 'exists:triggers,id'],
        ];
    }
}
