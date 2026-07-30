<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignEquipment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Remove an owned equipment instance — owner-only. Mirrors
 * UpdateArsenalModelRequest's route-bound-resource ownership check.
 */
class RemoveEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crew = $this->route('crew');
        $campaign = $this->route('campaign');
        $equipment = $this->route('equipment');

        return $crew instanceof CampaignCrew
            && $campaign instanceof Campaign
            && $crew->campaign_id === $campaign->id
            && $equipment instanceof CampaignEquipment
            && $equipment->campaign_crew_id === $crew->id
            && $this->user()
            && $this->user()->id === $crew->user_id;
    }

    public function rules(): array
    {
        return [];
    }
}
