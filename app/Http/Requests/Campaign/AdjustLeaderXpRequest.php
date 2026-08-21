<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Freeform Leadership Experience correction — crew-owner self-service
 * (unlike AdjustScripRequest's organizer-only gate: there's no separate
 * "experience" currency to protect against a stale-total race the way
 * scrip needs organizer arbitration; this just fills/unfills xp_track
 * boxes, same self-service tier as taking an advancement).
 */
class AdjustLeaderXpRequest extends FormRequest
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
            // Signed delta in BOXES (fill N more, or unfill N), not an
            // absolute count — same reasoning as AdjustScripRequest::amount.
            // 39 = the full xp_track length (CustomCharacter::defaultXpTrack()).
            'amount' => ['required', 'integer', 'min:-39', 'max:39', 'not_in:0'],
        ];
    }
}
