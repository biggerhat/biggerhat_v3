<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Starting Arsenal save payload.
 *
 * Hard rules (pg 15):
 * - 25 ss budget. Total cost of arsenal models ≤ 25.
 * - Unspent ss become scrip, capped at 3.
 * - Models must share at least one of the crew's two keywords OR be Versatile
 *   in the crew's declared faction.
 * - Masters/totems excluded (the leader already lives in custom_characters).
 * - Each model picked stores label only (no per-instance customization yet).
 */
class StoreStartingArsenalRequest extends FormRequest
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
            'hires' => ['nullable', 'array'],
            'hires.*.character_id' => ['required', 'integer', 'exists:characters,id'],
            'hires.*.label' => ['nullable', 'string', 'max:64'],
            'crew_card_effect_id' => ['required', 'integer', 'exists:campaign_crew_cards,id'],
        ];
    }
}
