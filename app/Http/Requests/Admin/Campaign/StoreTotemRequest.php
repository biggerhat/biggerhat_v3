<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreTotemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'is_black_joker' => ['required', 'boolean'],
            'is_red_joker' => ['required', 'boolean'],
            'df' => ['required', 'integer', 'min:1', 'max:10'],
            'wp' => ['required', 'integer', 'min:1', 'max:10'],
            'sp' => ['required', 'integer', 'min:1', 'max:10'],
            'health' => ['required', 'integer', 'min:1', 'max:30'],
            'abilities' => ['nullable', 'array'],
            'attack_actions' => ['nullable', 'array'],
            'tactical_actions' => ['nullable', 'array'],
            'special_replace_with_other_totem' => ['required', 'boolean'],
            'is_mini_master' => ['required', 'boolean'],
        ];
    }
}
