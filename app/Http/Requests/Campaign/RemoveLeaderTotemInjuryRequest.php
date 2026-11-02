<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Remove an injury from the crew's current Leader or Totem — owner-only. Mirrors RemoveManualInjuryRequest.
 */
class RemoveLeaderTotemInjuryRequest extends FormRequest
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
        return [];
    }
}
