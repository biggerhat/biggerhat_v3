<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pin a token or marker to the Crew Card by hand — owner-only.
 */
class AddCrewCardItemRequest extends FormRequest
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
            'type' => ['required', Rule::in(['token', 'marker'])],
            'id' => [
                'required',
                'integer',
                Rule::exists($this->input('type') === 'marker' ? 'markers' : 'tokens', 'id'),
            ],
        ];
    }
}
