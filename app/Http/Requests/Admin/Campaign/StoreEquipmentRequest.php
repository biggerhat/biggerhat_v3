<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'br' => ['nullable', 'integer', 'min:1', 'max:13'],
            'cc' => ['required', 'integer', 'min:0', 'max:10'],
            'is_always_available' => ['required', 'boolean'],
            'is_red_joker_entry' => ['required', 'boolean'],
            'ttw_only' => ['required', 'boolean'],
            'is_omens_mark' => ['required', 'boolean'],
            // Suit-pool eligibility — equipment lists e.g. "1 of P or C" in
            // the rulebook. Both suits stored so barter querying is simple.
            'pool_suit_a' => ['nullable', 'string', 'max:16'],
            'pool_suit_b' => ['nullable', 'string', 'max:16'],
            'is_unique' => ['required', 'boolean'],
            'leader_only' => ['required', 'boolean'],
            'non_unique_only' => ['required', 'boolean'],
            'annihilate_after_game' => ['required', 'boolean'],
            'body' => ['required', 'string'],
            'granted_ability' => ['nullable', 'array'],
            'granted_action' => ['nullable', 'array'],
        ];
    }
}
