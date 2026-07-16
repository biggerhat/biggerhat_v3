<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ad-hoc arsenal model add — for a mid-game event (no specific rulebook
 * mechanic) that grants a unit outside the normal Starting Arsenal/Weekly
 * Hire flow. No hireability restriction and no scrip cost by design: the
 * crew owner self-reports something that already happened at the table.
 */
class AddManualArsenalModelRequest extends FormRequest
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
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'label' => ['nullable', 'string', 'max:64'],
        ];
    }
}
