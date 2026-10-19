<?php

namespace App\Http\Requests\Admin;

use App\Enums\Campaign\CrewCardBorrowExclusionEnum;
use App\Enums\CrewUpgradeRestrictionEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Token;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrewRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'game_mode_type' => ['required', 'string', Rule::enum(GameModeTypeEnum::class)],
            'faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'description' => ['nullable', 'string'],
            'power_bar_count' => ['nullable', 'integer'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'tokens' => ['nullable', 'array'],
            'tokens.*' => ['string', Rule::exists(Token::class, 'name')],
            'markers' => ['nullable', 'array'],
            'markers.*' => ['string', Rule::exists(Marker::class, 'name')],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', Rule::exists(Character::class, 'display_name')],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', Rule::exists(Keyword::class, 'name')],
            'upgradeable_rows' => ['nullable', 'array'],
            'upgradeable_rows.*.type' => ['required', 'string', 'in:action,ability,trigger'],
            'upgradeable_rows.*.id' => ['required', 'integer'],
            'upgradeable_rows.*.restriction' => ['nullable', 'string', Rule::enum(CrewUpgradeRestrictionEnum::class)],
            'upgradeable_rows.*.is_signature' => ['sometimes', 'boolean'],
            'upgradeable_rows.*.borrow_exclusion' => ['nullable', 'string', Rule::enum(CrewCardBorrowExclusionEnum::class)],
            'hiring_rules_type' => ['nullable', 'string', 'in:fixed_crew,required_hires'],
            'alternate_leader' => ['nullable', 'integer', Rule::exists(Character::class, 'id')],
            'any_faction' => ['nullable'],
            'fixed_crew_keyword' => ['nullable', 'string'],
            'fixed_cache' => ['nullable', 'integer', 'min:0'],
            'required_characteristic' => ['nullable', 'string'],
            'required_count' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
