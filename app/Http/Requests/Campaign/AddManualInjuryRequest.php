<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Freeform injury add on an Arsenal Model — owner-only manual correction outside the Aftermath flow.
 */
class AddManualInjuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crew = $this->route('crew');
        $campaign = $this->route('campaign');
        $model = $this->route('model');

        return $crew instanceof CampaignCrew
            && $campaign instanceof Campaign
            && $crew->campaign_id === $campaign->id
            && $model instanceof CampaignArsenalModel
            && $model->campaign_crew_id === $crew->id
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
