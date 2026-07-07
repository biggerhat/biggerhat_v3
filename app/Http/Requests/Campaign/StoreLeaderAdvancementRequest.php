<?php

namespace App\Http\Requests\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Logging a single Leadership-Experience advancement from the Arsenal Sheet.
 * The controller + LeaderAdvancementService enforce the box-earned / one-per-box
 * / tier-gating rules; this just shapes the payload.
 */
class StoreLeaderAdvancementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crew = $this->route('crew');
        $campaign = $this->route('campaign');

        return $crew instanceof CampaignCrew
            && $campaign instanceof Campaign
            && $crew->campaign_id === $campaign->id
            && $this->user()
            && $this->user()->id === $crew->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position_in_xp_track' => ['required', 'integer', 'min:0', 'max:38'],
            'source_table' => ['required', 'string', Rule::enum(AdvancementTableEnum::class)],
            'catalog_id' => ['nullable', 'integer'],
            'applied_to_action_index' => ['nullable', 'integer'],
            // Attack/Tactical Mod target (pg 31, 38-43): defaults to the Leader.
            // Set applied_to_custom_character_id to route to the crew's Totem
            // instead (ownership verified server-side); set from_equipment_id +
            // applied_to_action_id to target an action a piece of owned
            // Equipment grants instead — mutually exclusive with the totem case.
            'applied_to_custom_character_id' => ['nullable', 'integer', 'exists:custom_characters,id'],
            'from_equipment_id' => ['nullable', 'integer', 'exists:campaign_equipment,id'],
            'applied_to_action_id' => ['nullable', 'integer', 'exists:actions,id'],
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            // Which Joker the player flipped for an Attack/Tactical Mod Joker-gated
            // row (pg 38-43) — Attack Mod's rows accept either color ("Any Joker");
            // Tactical Mod's are color-specific. Verified against the catalog row
            // server-side, not trusted on its own.
            'joker_color' => ['nullable', 'string', 'in:red,black'],
            // Any Joker (Action/Ability tables, pg 49/51): the free pick from an
            // eligible ally, resolved via the same search the Leader Builder uses.
            'free_choice' => ['nullable', 'array'],
            'free_choice.source_id' => ['nullable', 'integer'],
            'free_choice.source_character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'totem_name' => ['nullable', 'string', 'max:60'],
            'totem_size' => ['nullable', 'integer', 'min:1', 'max:5'],
            'totem_base' => ['nullable', 'string', 'in:30mm,40mm,50mm'],
        ];
    }
}
