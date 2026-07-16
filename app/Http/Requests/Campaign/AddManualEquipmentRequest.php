<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ad-hoc equipment add — mirrors AddManualArsenalModelRequest, for equipment
 * gained outside the normal Barter/Aftermath flow.
 */
class AddManualEquipmentRequest extends FormRequest
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
            'equipment_upgrade_id' => [
                'required',
                'integer',
                Rule::exists('upgrades', 'id')
                    ->where('game_mode_type', 'campaign')
                    ->where('campaign_upgrade_kind', 'equipment'),
            ],
        ];
    }
}
