<?php

namespace App\Http\Requests\Admin;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\CrewUpgradeModeEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CharacterRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'nicknames' => ['nullable', 'string', 'max:255'],
            'title_group_key' => ['nullable', 'string', 'max:255'],
            'station' => ['nullable', 'string', Rule::enum(CharacterStationEnum::class)],
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'totem' => ['nullable', 'string', Rule::exists(Character::class, 'slug')],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string'],
            'characteristics' => ['nullable', 'array'],
            'characteristics.*' => ['string'],
            'miniatures' => ['nullable', 'array'],
            'miniatures.*' => ['string'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['integer'],
            'signature_actions' => ['nullable', 'array'],
            'signature_actions.*' => ['integer'],
            'markers' => ['nullable', 'array'],
            'markers.*' => ['string'],
            'tokens' => ['nullable', 'array'],
            'tokens.*' => ['string'],
            'cost' => ['nullable', 'integer'],
            'health' => ['required', 'integer'],
            'size' => ['required', 'integer'],
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'speed' => ['required', 'integer'],
            'count' => ['required', 'integer'],
            'defense' => ['required', 'integer'],
            'defense_suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'willpower' => ['required', 'integer'],
            'willpower_suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'summon_target_number' => ['nullable', 'integer'],
            'generates_stone' => ['required', 'boolean'],
            'is_unhirable' => ['required', 'boolean'],
            'crew_upgrade_mode' => ['nullable', 'string', Rule::enum(CrewUpgradeModeEnum::class)],
            'is_beta' => ['required', 'boolean'],
            'game_mode_type' => ['required', 'string', Rule::enum(GameModeTypeEnum::class)],
            'is_hidden' => ['required', 'boolean'],
            'summons' => ['nullable', 'array'],
            'summons.*' => ['string'],
            'replaces_into' => ['nullable', 'array'],
            'replaces_into.*' => ['string'],
            'replaces_on_death' => ['nullable', 'array'],
            'replaces_on_death.*.slug' => ['required', 'string'],
            'replaces_on_death.*.count' => ['required', 'integer', 'min:1'],
            'replaces_on_death.*.health' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
