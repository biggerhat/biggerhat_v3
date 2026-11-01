<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrupted Pawns (optional rule, pg 146): after hiring a starting arsenal, the player flips an
 * uncheatable card at the table and takes the matching Those Who Thirst item, attached for good
 * to one arsenal model of cost 6 or less. Cost and eligibility are checked in the controller.
 */
class StoreCorruptedPawnRequest extends FormRequest
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

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'equipment_upgrade_id' => ['required', 'integer', Rule::exists('upgrades', 'id')
                ->where('game_mode_type', 'campaign')
                ->where('campaign_upgrade_kind', 'equipment')
                ->where('campaign_ttw_only', 1)
                ->where('campaign_is_omens_mark', 0)],
            'arsenal_model_id' => ['required', 'integer', 'exists:campaign_arsenal_models,id'],
        ];
    }
}
