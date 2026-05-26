<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInjuryRequest extends FormRequest
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
            'suit_pool' => ['required', 'string', Rule::in(['pc', 'te', 'black_joker', 'red_joker'])],
            'reflip_if_no_triggers' => ['required', 'boolean'],
            'reflip_if_master_or_totem' => ['required', 'boolean'],
            'is_traitor' => ['required', 'boolean'],
            'is_close_call' => ['required', 'boolean'],
            'annihilates_model' => ['required', 'boolean'],
        ];
    }
}
