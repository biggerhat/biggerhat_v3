<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackAlleyDoctorResultRequest extends FormRequest
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
            'flip_value_min' => ['nullable', 'integer', 'min:1', 'max:13'],
            'flip_value_max' => ['nullable', 'integer', 'min:1', 'max:13', 'gte:flip_value_min'],
            'is_black_joker' => ['required', 'boolean'],
            'is_red_joker' => ['required', 'boolean'],
            'outcome_kind' => ['required', 'string', Rule::in([
                'no_effect', 'removed', 'added_injury', 'gained_undead', 'gained_construct', 'lucky_miss_reflip',
            ])],
        ];
    }
}
