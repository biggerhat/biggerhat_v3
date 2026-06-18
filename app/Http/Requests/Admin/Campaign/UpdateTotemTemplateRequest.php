<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTotemTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            // Faction + base are inherited / chosen when the totem is added to a
            // crew (pg 52), so a template may leave them blank.
            'faction' => ['nullable', Rule::enum(FactionEnum::class)],
            'station' => ['nullable', 'string', 'max:100'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:20'],
            'health' => ['required', 'integer', 'min:1', 'max:30'],
            'defense' => ['required', 'integer', 'min:1', 'max:10'],
            'defense_suit' => ['nullable', 'string', 'max:10'],
            'willpower' => ['required', 'integer', 'min:1', 'max:10'],
            'willpower_suit' => ['nullable', 'string', 'max:10'],
            'speed' => ['required', 'integer', 'min:1', 'max:10'],
            'size' => ['nullable', 'integer', 'min:1', 'max:5'],
            'base' => ['nullable', 'string', 'max:10'],
            'campaign_totem_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'campaign_is_black_joker_totem' => ['required', 'boolean'],
            'campaign_is_red_joker_totem' => ['required', 'boolean'],
            'campaign_totem_special_replace' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
            // Linked Actions / Abilities (pg 52). signature_action_ids must be a
            // subset of action_ids; the controller flags those on the pivot.
            'action_ids' => ['nullable', 'array'],
            'action_ids.*' => ['integer', 'exists:actions,id'],
            'signature_action_ids' => ['nullable', 'array'],
            'signature_action_ids.*' => ['integer', 'exists:actions,id'],
            'ability_ids' => ['nullable', 'array'],
            'ability_ids.*' => ['integer', 'exists:abilities,id'],
        ];
    }
}
