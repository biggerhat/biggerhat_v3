<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rename an already-hired Arsenal Model (the `label` nickname field) —
 * owner-only. Mirrors AddManualArsenalModelRequest's crew-ownership check,
 * plus confirms the model itself belongs to the route's crew.
 */
class UpdateArsenalModelRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:64'],
        ];
    }
}
