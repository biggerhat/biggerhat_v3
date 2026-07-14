<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\Campaign\CrewCardBorrowExclusionEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrewCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // The master this card is printed on can be an official master
            // OR a custom-built Campaign Leader — master_type picks which
            // table master_id is validated (and later, morphed) against.
            // Defaults to 'official' when omitted (matches the pre-polymorphism
            // behavior, where master_id always meant a Character).
            'master_type' => ['nullable', 'string', 'in:official,custom'],
            'master_id' => [
                'nullable', 'integer',
                Rule::when(
                    fn () => $this->input('master_type') === 'custom',
                    [Rule::exists('custom_characters', 'id')->where('is_campaign_leader', true)],
                    [Rule::exists('characters', 'id')->where('station', CharacterStationEnum::Master->value)],
                ),
            ],
            'requires_token_choice' => ['required', 'boolean'],
            'requires_marker_choice' => ['required', 'boolean'],
            'requires_upgrade_type_choice' => ['required', 'boolean'],
            'actions' => ['nullable', 'array'],
            'actions.*.id' => ['required', 'integer', 'exists:actions,id'],
            'actions.*.is_signature' => ['required', 'boolean'],
            // Tier-4 Crew Card Advancement (pg 32, 54) may not borrow an
            // effect referencing a power bar or causing a card swap.
            'actions.*.borrow_exclusion' => ['nullable', Rule::enum(CrewCardBorrowExclusionEnum::class)],
            'abilities' => ['nullable', 'array'],
            'abilities.*.id' => ['required', 'integer', 'exists:abilities,id'],
            'abilities.*.borrow_exclusion' => ['nullable', Rule::enum(CrewCardBorrowExclusionEnum::class)],
        ];
    }
}
