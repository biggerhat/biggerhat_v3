<?php

namespace App\Http\Requests\Campaign;

use App\Enums\BaseSizeEnum;
use App\Enums\FactionEnum;
use App\Enums\LeaderArchetypeEnum;
use App\Enums\LeaderTagEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\LeaderArchetype;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the Leader Builder save payload. Combines:
 * - Standard custom-character shape (name + actions/abilities/etc. JSON)
 * - Campaign-specific extras (archetype, tag, stats, size)
 * - Cross-field rules: action/ability count + cost-cap matching the archetype.
 */
class StoreLeaderRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'archetype' => ['required', 'string', Rule::enum(LeaderArchetypeEnum::class)],
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'tag' => ['required', 'string', Rule::enum(LeaderTagEnum::class)],
            // Two keywords. The pair is the crew's declared keyword set;
            // Leader Build is the canonical point to (re)declare them, so the
            // controller overwrites `crew.keyword_1_id` / `keyword_2_id` with
            // these values on save (LeaderBuilderController::update()). No
            // pre-existing crew pair check here — Leader Build IS the declaration.
            'keyword_1_id' => ['required', 'integer', 'exists:keywords,id'],
            'keyword_2_id' => ['required', 'integer', 'exists:keywords,id', 'different:keyword_1_id'],
            // Size + base — base values stored as enum on CustomCharacter
            // (BaseSizeEnum: 30mm|40mm|50mm); size is integer 1..4.
            'size' => ['required', 'integer', 'min:1', 'max:4'],
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'characteristics' => ['nullable', 'array', 'max:2'],
            'characteristics.*' => ['string', 'max:64'],
            // Actions / abilities — shape matches CardCreator/Editor; source_id
            // is the original Action/Ability row this was picked from.
            'actions' => ['nullable', 'array'],
            'actions.*.name' => ['required', 'string'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.category' => ['required', 'string', Rule::in(['attack', 'tactical'])],
            'actions.*.stone_cost' => ['nullable', 'integer'],
            'actions.*.source_id' => ['nullable', 'integer', 'exists:actions,id'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.range' => ['nullable'],
            'actions.*.range_type' => ['nullable', 'string'],
            'actions.*.stat' => ['nullable'],
            'actions.*.stat_suits' => ['nullable', 'string'],
            'actions.*.stat_modifier' => ['nullable', 'string'],
            'actions.*.resisted_by' => ['nullable', 'string'],
            'actions.*.target_number' => ['nullable'],
            'actions.*.target_suits' => ['nullable', 'string'],
            'actions.*.damage' => ['nullable', 'string'],
            'actions.*.is_signature' => ['boolean'],
            'actions.*.triggers' => ['nullable', 'array'],
            'actions.*.triggers.*.name' => ['required', 'string'],
            'actions.*.triggers.*.suits' => ['nullable', 'string'],
            'actions.*.triggers.*.stone_cost' => ['nullable'],
            'actions.*.triggers.*.description' => ['nullable', 'string'],
            'actions.*.triggers.*.source_id' => ['nullable', 'integer'],
            'abilities' => ['nullable', 'array'],
            'abilities.*.name' => ['required', 'string'],
            'abilities.*.suits' => ['nullable', 'string'],
            'abilities.*.defensive_ability_type' => ['nullable', 'string'],
            'abilities.*.costs_stone' => ['boolean'],
            'abilities.*.description' => ['nullable', 'string'],
            'abilities.*.source_id' => ['nullable', 'integer', 'exists:abilities,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $archetype = $this->loadArchetype($this->input('archetype'));
            if (! $archetype) {
                return;
            }

            $actions = collect($this->input('actions', []));
            $attack = $actions->where('category', 'attack');
            $tactical = $actions->where('category', 'tactical');
            $abilities = collect($this->input('abilities', []));

            if ($attack->count() > $archetype->attack_actions_count) {
                $validator->errors()->add(
                    'actions',
                    "Archetype {$archetype->name} allows at most {$archetype->attack_actions_count} attack action(s)."
                );
            }
            foreach ($attack as $i => $a) {
                if (($a['stone_cost'] ?? 0) > $archetype->attack_action_cost_cap) {
                    $validator->errors()->add(
                        "actions.{$i}.stone_cost",
                        "Attack action exceeds the cost cap of {$archetype->attack_action_cost_cap}."
                    );
                }
            }

            if ($tactical->count() > $archetype->tactical_actions_count) {
                $validator->errors()->add(
                    'actions',
                    "Archetype {$archetype->name} allows at most {$archetype->tactical_actions_count} tactical action(s)."
                );
            }
            foreach ($tactical as $i => $a) {
                if (($a['stone_cost'] ?? 0) > $archetype->tactical_action_cost_cap) {
                    $validator->errors()->add(
                        "actions.{$i}.stone_cost",
                        "Tactical action exceeds the cost cap of {$archetype->tactical_action_cost_cap}."
                    );
                }
            }

            if ($abilities->count() > $archetype->abilities_count) {
                $validator->errors()->add(
                    'abilities',
                    "Archetype {$archetype->name} allows at most {$archetype->abilities_count} abilit(y/ies)."
                );
            }
        });
    }

    private function loadArchetype(?string $slug): ?LeaderArchetype
    {
        if (! $slug) {
            return null;
        }

        return LeaderArchetype::query()->where('slug', $slug)->first();
    }
}
