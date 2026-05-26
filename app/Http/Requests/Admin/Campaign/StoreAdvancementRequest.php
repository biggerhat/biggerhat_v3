<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use App\Enums\SuitEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the four advancement-* catalog tables. Each table
 * has the same column set so one rule list covers all of them. The controller
 * subclass picks the model; this only validates shape.
 */
class StoreAdvancementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'is_always_available' => ['required', 'boolean'],
            'is_black_joker' => ['required', 'boolean'],
            'is_red_joker' => ['required', 'boolean'],
            // trigger | skl | signature | choice | joker
            'modifier_type' => ['required', 'string', Rule::in(['trigger', 'skl', 'signature', 'choice', 'joker'])],
            'suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'skl_from' => ['nullable', 'integer', 'min:0', 'max:10'],
            'skl_to' => ['nullable', 'integer', 'min:0', 'max:10'],
            'grants_signature' => ['required', 'boolean'],
            'joker_freechoice' => ['required', 'boolean'],
            'stat_block' => ['nullable', 'array'],
            'defensive_ability_type' => ['nullable', 'string', 'max:64'],
        ];
    }
}
