<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ad-hoc arsenal model add — for a mid-game event (no specific rulebook
 * mechanic) that grants a unit outside the normal Starting Arsenal/Weekly
 * Hire flow. No hireability restriction and no scrip cost by design: the
 * crew owner self-reports something that already happened at the table.
 */
class AddManualArsenalModelRequest extends FormRequest
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
            // Exactly one of character_id (official catalog) / custom_character_id
            // (the owner's own Card Creator homebrew) is required.
            'character_id' => ['required_without:custom_character_id', 'prohibits:custom_character_id', 'nullable', 'integer', 'exists:characters,id'],
            'custom_character_id' => ['required_without:character_id', 'nullable', 'integer', 'exists:custom_characters,id'],
            'label' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A homebrew card hired into a crew must belong to the crew's own
            // owner — otherwise any user could hire someone else's private card.
            $customCharacterId = $this->input('custom_character_id');
            if ($customCharacterId === null) {
                return;
            }
            $owned = CustomCharacter::query()
                ->whereKey($customCharacterId)
                ->where('user_id', $this->user()->id)
                ->exists();
            if (! $owned) {
                $validator->errors()->add('custom_character_id', 'That custom character does not belong to you.');
            }
        });
    }
}
