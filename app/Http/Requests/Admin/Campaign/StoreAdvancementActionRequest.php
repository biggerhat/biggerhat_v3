<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdvancementActionRequest extends FormRequest
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
            'action_id' => ['nullable', 'integer', 'exists:actions,id'],
            // Bespoke stat block — only meaningful when action_id is null.
            'stat_block' => ['nullable', 'array'],
            // Bespoke rows only (pg 31): whether this grants a Signature
            // Action. Lookup rows (action_id set) instead inherit the
            // linked Action's own is_signature flag — see LeaderAdvancementService.
            'is_signature' => ['required', 'boolean'],
        ];
    }
}
