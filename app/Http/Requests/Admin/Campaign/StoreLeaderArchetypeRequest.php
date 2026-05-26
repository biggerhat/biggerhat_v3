<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\LeaderArchetypeEnum;
use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaderArchetypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', Rule::enum(LeaderArchetypeEnum::class), Rule::unique('leader_archetypes', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'df' => ['required', 'integer', 'min:1', 'max:10'],
            'wp' => ['required', 'integer', 'min:1', 'max:10'],
            'sp' => ['required', 'integer', 'min:1', 'max:10'],
            'health' => ['required', 'integer', 'min:1', 'max:30'],
            'attack_actions_count' => ['required', 'integer', 'min:0', 'max:5'],
            'attack_action_cost_cap' => ['required', 'integer', 'min:0', 'max:15'],
            'attack_gets_trigger' => ['required', 'boolean'],
            'tactical_actions_count' => ['required', 'integer', 'min:0', 'max:5'],
            'tactical_action_cost_cap' => ['required', 'integer', 'min:0', 'max:15'],
            'abilities_count' => ['required', 'integer', 'min:0', 'max:5'],
            'ability_cost_cap' => ['required', 'integer', 'min:0', 'max:15'],
            'special_notes' => ['nullable', 'string'],
        ];
    }
}
