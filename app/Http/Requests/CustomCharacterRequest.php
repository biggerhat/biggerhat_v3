<?php

namespace App\Http\Requests;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Models\CustomCharacter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by CustomCharacterController::store/update — same shape both ways.
 * On update, the route-bound CustomCharacter goes through the normal
 * ownership policy here rather than a second explicit check in the
 * controller. On store there's no model yet, so any authenticated user
 * passes (already gated by the `auth` route middleware).
 */
class CustomCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customCharacter = $this->route('customCharacter');

        return $customCharacter instanceof CustomCharacter
            ? ($this->user() !== null && $this->user()->can('update', $customCharacter))
            : $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'second_faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'station' => ['nullable', 'string', Rule::enum(CharacterStationEnum::class)],
            'cost' => ['nullable', 'integer', 'min:0', 'max:99'],
            'health' => ['required', 'integer', 'min:1', 'max:99'],
            'size' => ['nullable', 'integer', 'min:0', 'max:10'],
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'defense' => ['required', 'integer', 'min:0', 'max:20'],
            'defense_suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'willpower' => ['required', 'integer', 'min:0', 'max:20'],
            'willpower_suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'speed' => ['required', 'integer', 'min:0', 'max:20'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'summon_target_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'generates_stone' => ['boolean'],
            'is_unhirable' => ['boolean'],
            'actions' => ['nullable', 'array', 'max:20'],
            'actions.*.name' => ['required', 'string', 'max:255'],
            'actions.*.type' => ['required', 'string', Rule::enum(ActionTypeEnum::class)],
            'actions.*.is_signature' => ['boolean'],
            // stone_cost is rendered as `v-for="n in stone_cost"` (one soulstone
            // icon per point) — an unbounded value here isn't just bad data, it's
            // a client-side hang/crash for anyone who opens the card, including
            // an unauthenticated visitor on the public share link.
            'actions.*.stone_cost' => ['nullable', 'integer', 'min:0', 'max:10'],
            'actions.*.range' => ['nullable', 'max:20'],
            'actions.*.range_type' => ['nullable', 'string', Rule::enum(ActionRangeTypeEnum::class)],
            'actions.*.stat' => ['nullable', 'max:20'],
            'actions.*.stat_suits' => ['nullable', 'string', 'max:20'],
            'actions.*.stat_modifier' => ['nullable', 'string', 'max:20'],
            'actions.*.resisted_by' => ['nullable', 'string', 'max:20'],
            'actions.*.target_number' => ['nullable', 'max:20'],
            'actions.*.target_suits' => ['nullable', 'string', 'max:20'],
            'actions.*.damage' => ['nullable', 'string', 'max:50'],
            'actions.*.description' => ['nullable', 'string', 'max:2000'],
            'actions.*.source_id' => ['nullable', 'integer'],
            'actions.*.triggers' => ['nullable', 'array', 'max:10'],
            'actions.*.triggers.*.name' => ['required', 'string', 'max:255'],
            'actions.*.triggers.*.suits' => ['nullable', 'string', 'max:20'],
            'actions.*.triggers.*.stone_cost' => ['nullable', 'integer', 'min:0', 'max:10'],
            'actions.*.triggers.*.description' => ['nullable', 'string', 'max:2000'],
            'actions.*.triggers.*.source_id' => ['nullable', 'integer'],
            'abilities' => ['nullable', 'array', 'max:20'],
            'abilities.*.name' => ['required', 'string', 'max:255'],
            'abilities.*.suits' => ['nullable', 'string', 'max:20'],
            'abilities.*.defensive_ability_type' => ['nullable', 'string', Rule::enum(DefensiveAbilityTypeEnum::class)],
            'abilities.*.costs_stone' => ['boolean'],
            'abilities.*.description' => ['nullable', 'string', 'max:2000'],
            'abilities.*.source_id' => ['nullable', 'integer'],
            'keywords' => ['nullable', 'array', 'max:10'],
            'keywords.*.id' => ['nullable', 'integer'],
            'keywords.*.name' => ['required', 'string', 'max:255'],
            'characteristics' => ['nullable', 'array', 'max:10'],
            'characteristics.*' => ['string', 'max:255'],
            'linked_crew_upgrades' => ['nullable', 'array', 'max:10'],
            'linked_crew_upgrades.*.source_type' => ['required', 'string', 'in:official,custom'],
            'linked_crew_upgrades.*.id' => ['required', 'integer'],
            'linked_crew_upgrades.*.name' => ['required', 'string', 'max:255'],
            'linked_totems' => ['nullable', 'array', 'max:10'],
            'linked_totems.*.source_type' => ['required', 'string', 'in:official,custom'],
            'linked_totems.*.id' => ['required', 'integer'],
            'linked_totems.*.name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
