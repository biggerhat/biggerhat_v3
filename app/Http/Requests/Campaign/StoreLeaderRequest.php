<?php

namespace App\Http\Requests\Campaign;

use App\Enums\BaseSizeEnum;
use App\Enums\Campaign\LeaderArchetypeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\FactionEnum;
use App\Models\Ability;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Character;
use App\Models\CustomCharacter;
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
            $archetype = LeaderArchetypeEnum::tryFrom((string) $this->input('archetype'));
            if (! $archetype) {
                return;
            }

            $actions = collect($this->input('actions', []));
            $attack = $actions->where('category', 'attack');
            $tactical = $actions->where('category', 'tactical');
            $abilities = collect($this->input('abilities', []));
            $name = $archetype->label();

            if ($attack->count() > $archetype->attackActionsCount()) {
                $validator->errors()->add(
                    'actions',
                    "Archetype {$name} allows at most {$archetype->attackActionsCount()} attack action(s)."
                );
            }
            foreach ($attack as $i => $a) {
                if (($a['stone_cost'] ?? 0) > $archetype->attackActionCostCap()) {
                    $validator->errors()->add(
                        "actions.{$i}.stone_cost",
                        "Attack action exceeds the cost cap of {$archetype->attackActionCostCap()}."
                    );
                }
            }

            if ($tactical->count() > $archetype->tacticalActionsCount()) {
                $validator->errors()->add(
                    'actions',
                    "Archetype {$name} allows at most {$archetype->tacticalActionsCount()} tactical action(s)."
                );
            }
            foreach ($tactical as $i => $a) {
                if (($a['stone_cost'] ?? 0) > $archetype->tacticalActionCostCap()) {
                    $validator->errors()->add(
                        "actions.{$i}.stone_cost",
                        "Tactical action exceeds the cost cap of {$archetype->tacticalActionCostCap()}."
                    );
                }
            }

            if ($abilities->count() > $archetype->abilitiesCount()) {
                $validator->errors()->add(
                    'abilities',
                    "Archetype {$name} allows at most {$archetype->abilitiesCount()} abilit(y/ies)."
                );
            }
            foreach ($abilities as $i => $ab) {
                $sourceId = $ab['source_id'] ?? null;
                if ($sourceId) {
                    $cost = Ability::query()->whereKey((int) $sourceId)->value('cost') ?? 0;
                    if ($cost > $archetype->abilityCostCap()) {
                        $validator->errors()->add(
                            "abilities.{$i}.source_id",
                            "Ability exceeds the cost cap of {$archetype->abilityCostCap()} ss."
                        );
                    }
                }
            }

            // Leader name must not match an existing Malifaux model (pg 17).
            $leaderName = (string) $this->input('name', '');
            if ($leaderName !== '') {
                $nameClash = Character::query()
                    ->whereRaw('LOWER(display_name) = ?', [strtolower($leaderName)])
                    ->exists();
                if ($nameClash) {
                    $validator->errors()->add('name', 'This name already belongs to an existing Malifaux model. Choose a different name.');
                }
            }

            // At least one chosen keyword must have a model in the declared faction (pg 15).
            $faction = $this->input('faction');
            $keyword1 = $this->input('keyword_1_id');
            $keyword2 = $this->input('keyword_2_id');
            $keywordIds = array_filter([(int) $keyword1, (int) $keyword2]);
            if ($faction && ! empty($keywordIds)) {
                $hasModelInFaction = Character::query()
                    ->whereHas('keywords', fn ($q) => $q->whereIn('keywords.id', $keywordIds))
                    ->where('faction', $faction)
                    ->exists();
                if (! $hasModelInFaction) {
                    $validator->errors()->add(
                        'keyword_1_id',
                        'At least one chosen keyword must have a model belonging to the declared faction.'
                    );
                }
            }
        });
    }
}
