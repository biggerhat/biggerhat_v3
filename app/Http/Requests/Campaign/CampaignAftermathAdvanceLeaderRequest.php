<?php

namespace App\Http\Requests\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Services\CampaignRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignAftermathAdvanceLeaderRequest extends FormRequest
{
    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            // In-game facts the system can't derive on its own (the tracker
            // doesn't capture "Interacted within 6\" of the enemy DZ", and a
            // solo loss is indistinguishable from a draw). The conditional
            // bonuses are gated server-side by the Leader's actual tag and the
            // total is computed by CampaignRules — the client never sends a raw
            // XP total, so it can't inflate it or claim the wrong tag's bonus.
            'bruiser_killed_non_peon' => ['required', 'boolean'],
            'strategist_interacted' => ['required', 'boolean'],
            'lost' => ['required', 'boolean'],
            'advancements' => ['nullable', 'array'],
            'advancements.*.source_table' => ['required', 'string', Rule::enum(AdvancementTableEnum::class)],
            'advancements.*.catalog_id' => ['nullable', 'integer'],
            'advancements.*.applied_to_action_index' => ['nullable', 'integer'],
            // Attack/Tactical Mod target (pg 31, 38-43) — see the sibling rule
            // block in StoreLeaderAdvancementRequest for the full explanation.
            'advancements.*.applied_to_custom_character_id' => ['nullable', 'integer', 'exists:custom_characters,id'],
            'advancements.*.applied_to_action_id' => ['nullable', 'integer', 'exists:actions,id'],
            'advancements.*.position_in_xp_track' => ['required', 'integer', 'min:0', 'max:38'],
            // Any Joker (Action/Ability tables, pg 49/51): the free pick from
            // an eligible ally, resolved via the Leader Builder's search.
            'advancements.*.free_choice' => ['nullable', 'array'],
            'advancements.*.free_choice.source_id' => ['nullable', 'integer'],
            'advancements.*.free_choice.source_character_id' => ['nullable', 'integer', 'exists:characters,id'],
            // Optional: the originating equipment for an attack/tactical
            // modifier that targets an equipment-derived action (pg 31).
            'advancements.*.from_equipment_id' => ['nullable', 'integer', 'exists:campaign_equipment,id'],
            // Optional: required for Totem source_table — server validates
            // the flip-value matches the chosen totem template exactly.
            'advancements.*.flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            // Which Joker the player flipped for an Attack/Tactical Mod Joker-gated
            // row (pg 38-43) — verified against the catalog row server-side.
            'advancements.*.joker_color' => ['nullable', 'string', 'in:red,black'],
            'advancements.*.totem_name' => ['nullable', 'string', 'max:100'],
            'advancements.*.totem_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'advancements.*.totem_base' => ['nullable', 'string', 'max:10'],
            // Crew Card table (pg 17-18): the token/marker/upgrade-type pick a
            // borrowed effect requires, if any — see StoreLeaderAdvancementRequest.
            'advancements.*.crew_card_choice' => ['nullable', 'array'],
            'advancements.*.crew_card_choice.id' => ['nullable'],
            // Crew Card table (pg 32, 54): which of the two Tier-4 pools
            // catalog_id resolves against — see StoreLeaderAdvancementRequest.
            'advancements.*.crew_card_source' => ['nullable', 'string', 'in:campaign_crew_card,crew_upgrade'],
            // crew_upgrade source only (pg 32): catalog_id above is the picked
            // item's own id (an Action/Ability/Trigger row) — these two pin
            // down which single item and which source card it came from.
            'advancements.*.crew_card_item_type' => ['nullable', 'string', 'in:action,ability,trigger'],
            'advancements.*.crew_card_upgrade_id' => ['nullable', 'integer'],

        ];
    }
}
