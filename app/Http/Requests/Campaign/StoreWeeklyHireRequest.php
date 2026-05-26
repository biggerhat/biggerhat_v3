<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyHireRequest extends FormRequest
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
            // At least one hire — pg 18: "every player must add at least one
            // model" at the start of a new week.
            'hires' => ['required', 'array', 'min:1'],
            'hires.*.character_id' => ['required', 'integer', 'exists:characters,id'],
            'hires.*.label' => ['nullable', 'string', 'max:64'],
        ];
    }
}
