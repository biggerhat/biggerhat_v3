<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Freeform scrip adjustment — organizer-only (a manual correction the
 * organizer makes on a crew's behalf, not a player spend), mirroring
 * CampaignPolicy::update()'s organizer-vs-player check.
 */
class AdjustScripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $crew = $this->route('crew');
        $campaign = $this->route('campaign');

        return $crew instanceof CampaignCrew
            && $campaign instanceof Campaign
            && $crew->campaign_id === $campaign->id
            && $this->user()
            && $this->user()->can('update', $campaign);
    }

    public function rules(): array
    {
        return [
            // Signed delta applied to the crew's current scrip (e.g. -5 to
            // spend, 5 to award) rather than an absolute value — avoids a
            // stale-total race against any other scrip mutation in flight.
            'amount' => ['required', 'integer', 'min:-999', 'max:999', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
