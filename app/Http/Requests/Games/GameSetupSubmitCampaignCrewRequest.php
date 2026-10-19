<?php

namespace App\Http\Requests\Games;

use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;

class GameSetupSubmitCampaignCrewRequest extends FormRequest
{
    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            // CampaignArsenalModel row ids, not Character catalog ids — one
            // entry per physical model hired, so owning several copies of the
            // same catalog Character hires exactly the ones picked instead of
            // every owned copy at once.
            'arsenal_model_ids' => ['present', 'array'],
            'arsenal_model_ids.*' => ['integer', 'exists:campaign_arsenal_models,id'],
            // Owned equipment (pg 19) optionally attached to a specific hire
            // (or the Leader) at selection time, instead of only during play.
            'equipment_assignments' => ['nullable', 'array'],
            'equipment_assignments.*.equipment_id' => ['required', 'integer'],
            'equipment_assignments.*.target' => ['required', 'string'],

        ];
    }
}
