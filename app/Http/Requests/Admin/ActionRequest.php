<?php

namespace App\Http\Requests\Admin;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\ModifierTypeEnum;
use App\Enums\ResistanceTypeEnum;
use App\Models\Character;
use App\Models\Trigger;
use App\Rules\SingleMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActionRequest extends FormRequest
{
    /**
     * Route middleware (permission:edit_*) gates access; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'game_mode_type' => ['required', 'string', Rule::enum(GameModeTypeEnum::class)],
            'type' => ['required', 'string', Rule::enum(ActionTypeEnum::class)],
            'is_signature' => ['required', 'boolean'],
            'stone_cost' => ['required', 'integer', 'min:0', 'max:10'],
            'range' => ['nullable', 'string'],
            'range_type' => ['nullable', 'string', Rule::enum(ActionRangeTypeEnum::class)],
            'stat' => ['nullable', 'string'],
            'stat_suits' => ['nullable', 'string'],
            'stat_modifier' => ['nullable', 'string', Rule::enum(ModifierTypeEnum::class)],
            'resisted_by' => ['nullable', 'string', Rule::enum(ResistanceTypeEnum::class)],
            'target_number' => ['nullable', 'string'],
            'target_suits' => ['nullable', 'string'],
            'damage' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'triggers' => ['nullable', 'array'],
            'triggers.*' => ['string', new SingleMatch(Trigger::class, 'name')],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', new SingleMatch(Character::class, 'display_name')],
            // Campaign-only — Summoning advancement is the only remaining shared-catalog
            // kind (Action advancement lives on its own table). Cleared by the
            // controller unless the mode is campaign.
            'campaign_advancement_kind' => ['nullable', 'string', 'in:summoning'],
        ];
    }
}
