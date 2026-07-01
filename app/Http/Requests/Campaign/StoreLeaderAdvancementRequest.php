<?php

namespace App\Http\Requests\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Logging a single Leadership-Experience advancement from the Arsenal Sheet.
 * The controller + LeaderAdvancementService enforce the box-earned / one-per-box
 * / tier-gating rules; this just shapes the payload.
 */
class StoreLeaderAdvancementRequest extends FormRequest
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position_in_xp_track' => ['required', 'integer', 'min:0', 'max:38'],
            'source_table' => ['required', 'string', Rule::enum(AdvancementTableEnum::class)],
            'catalog_id' => ['nullable', 'integer'],
            'applied_to_action_index' => ['nullable', 'integer'],
            'from_equipment_id' => ['nullable', 'integer', 'exists:campaign_equipment,id'],
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'free_choice' => ['nullable', 'array'],
            'totem_name' => ['nullable', 'string', 'max:60'],
            'totem_size' => ['nullable', 'integer', 'min:1', 'max:5'],
            'totem_base' => ['nullable', 'string', 'in:30mm,40mm,50mm'],
        ];
    }
}
