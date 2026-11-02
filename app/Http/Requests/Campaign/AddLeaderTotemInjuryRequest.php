<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Freeform injury add on the crew's current Leader or Totem — owner-only manual correction outside the
 * Aftermath flow. Mirrors AddManualInjuryRequest for Arsenal Models.
 */
class AddLeaderTotemInjuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crew = $this->route('crew');
        $campaign = $this->route('campaign');
        $character = $this->route('character');

        return $crew instanceof CampaignCrew
            && $campaign instanceof Campaign
            && $crew->campaign_id === $campaign->id
            && $character instanceof CustomCharacter
            && $character->campaign_crew_id === $crew->id
            && $character->current
            && ($character->is_campaign_leader || $character->is_campaign_totem)
            && $this->user()
            && $this->user()->id === $crew->user_id;
    }

    public function rules(): array
    {
        return [
            'injury_upgrade_id' => [
                'required',
                'integer',
                Rule::exists('upgrades', 'id')
                    ->where('game_mode_type', 'campaign')
                    ->where('campaign_upgrade_kind', 'injury'),
            ],
        ];
    }
}
