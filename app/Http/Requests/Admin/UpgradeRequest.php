<?php

namespace App\Http\Requests\Admin;

use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpgradeRequest extends FormRequest
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
            'plentiful' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::enum(UpgradeTypeEnum::class)],
            'limitations' => ['nullable', 'string', Rule::enum(UpgradeLimitationEnum::class)],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'tokens' => ['nullable', 'array'],
            'tokens.*' => ['string', Rule::exists(Token::class, 'name')],
            'markers' => ['nullable', 'array'],
            'markers.*' => ['string', Rule::exists(Marker::class, 'name')],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['integer', Rule::exists(Action::class, 'id')],
            'signature_actions' => ['nullable', 'array'],
            'signature_actions.*' => ['integer', Rule::exists(Action::class, 'id')],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::exists(Ability::class, 'name')],
            'triggers' => ['nullable', 'array'],
            'triggers.*' => ['string', Rule::exists(Trigger::class, 'name')],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', Rule::exists(Character::class, 'display_name')],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', Rule::exists(Keyword::class, 'name')],
            // Campaign-only — accept but zero out below unless mode is campaign.
            'campaign_upgrade_kind' => ['nullable', 'string', 'in:equipment,injury'],
            'campaign_br' => ['nullable', 'integer', 'min:1', 'max:13'],
            'campaign_cc' => ['nullable', 'integer', 'min:0'],
            'campaign_pool_suit_a' => ['nullable', 'string', 'max:12'],
            'campaign_pool_suit_b' => ['nullable', 'string', 'max:12'],
            'campaign_is_always_available' => ['sometimes', 'boolean'],
            'campaign_ttw_only' => ['sometimes', 'boolean'],
            'campaign_is_omens_mark' => ['sometimes', 'boolean'],
            'campaign_is_unique' => ['sometimes', 'boolean'],
            'campaign_leader_only' => ['sometimes', 'boolean'],
            'campaign_non_unique_only' => ['sometimes', 'boolean'],
            'campaign_annihilate_after_game' => ['sometimes', 'boolean'],
            'campaign_is_red_joker_entry' => ['sometimes', 'boolean'],
            'campaign_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'campaign_suit_pool' => ['nullable', 'string', 'in:pc,te,black_joker,red_joker'],
            'campaign_is_traitor' => ['sometimes', 'boolean'],
            'campaign_is_close_call' => ['sometimes', 'boolean'],
            'campaign_annihilates_model' => ['sometimes', 'boolean'],
            'campaign_reflip_if_no_triggers' => ['sometimes', 'boolean'],
            'campaign_reflip_if_master_or_totem' => ['sometimes', 'boolean'],
        ];
    }
}
