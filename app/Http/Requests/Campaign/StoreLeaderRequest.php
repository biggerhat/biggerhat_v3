<?php

namespace App\Http\Requests\Campaign;

use App\Enums\BaseSizeEnum;
use App\Enums\Campaign\LeaderArchetypeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
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
            'actions.*.source_character_id' => ['nullable', 'integer', 'exists:characters,id'],
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
            'abilities.*.source_character_id' => ['nullable', 'integer', 'exists:characters,id'],
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
                // The cap is the SOURCE MODEL's cost ("an ally of cost N or less",
                // pg 17), read off the chosen source character.
                $sourceCharacterId = $a['source_character_id'] ?? null;
                if ($sourceCharacterId
                    && (Character::query()->whereKey((int) $sourceCharacterId)->value('cost') ?? 0) > $archetype->attackActionCostCap()) {
                    $validator->errors()->add(
                        "actions.{$i}.source_character_id",
                        "Attack action's source model exceeds the cost cap of {$archetype->attackActionCostCap()} ss."
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
                $sourceCharacterId = $a['source_character_id'] ?? null;
                if ($sourceCharacterId
                    && (Character::query()->whereKey((int) $sourceCharacterId)->value('cost') ?? 0) > $archetype->tacticalActionCostCap()) {
                    $validator->errors()->add(
                        "actions.{$i}.source_character_id",
                        "Tactical action's source model exceeds the cost cap of {$archetype->tacticalActionCostCap()} ss."
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
                // The cap is the SOURCE MODEL's cost ("an ally of cost N or less",
                // pg 17) — abilities carry no cost of their own — so read it off
                // the chosen source character.
                $sourceCharacterId = $ab['source_character_id'] ?? null;
                if ($sourceCharacterId) {
                    $cost = Character::query()->whereKey((int) $sourceCharacterId)->value('cost') ?? 0;
                    if ($cost > $archetype->abilityCostCap()) {
                        $validator->errors()->add(
                            "abilities.{$i}.source_character_id",
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

            // Actions/abilities must come from a valid source (pg 17): a
            // cost-bearing model that shares a chosen keyword and is neither a
            // master nor a totem. Verified authoritatively against the submitted
            // source_character_id rather than trusting client cost fields.
            $verifySource = function (?int $characterId, ?int $sourceId, string $relation, string $field) use ($validator, $keywordIds) {
                // Custom entries with no catalog source are exempt from the rule.
                if ($sourceId === null) {
                    return;
                }
                if ($characterId === null || empty($keywordIds)) {
                    $validator->errors()->add($field, 'This must be chosen from an existing model.');

                    return;
                }
                $valid = Character::query()
                    ->whereKey($characterId)
                    ->where('cost', '>=', 1)
                    ->whereNotIn('station', [CharacterStationEnum::Master->value])
                    ->whereDoesntHave('keywords', fn ($k) => $k->where('name', 'like', '%Totem%'))
                    ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
                    ->whereHas($relation, fn ($r) => $r->whereKey($sourceId))
                    ->exists();
                if (! $valid) {
                    $validator->errors()->add($field, 'Must be taken from a non-master, non-totem ally (with a cost) that shares a chosen keyword.');
                }
            };

            foreach ((array) $this->input('actions', []) as $i => $a) {
                $verifySource($a['source_character_id'] ?? null, $a['source_id'] ?? null, 'actions', "actions.{$i}.source_character_id");
            }
            foreach ((array) $this->input('abilities', []) as $i => $ab) {
                $verifySource($ab['source_character_id'] ?? null, $ab['source_id'] ?? null, 'abilities', "abilities.{$i}.source_character_id");
            }
        });
    }
}
