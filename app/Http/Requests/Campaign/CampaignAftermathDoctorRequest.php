<?php

namespace App\Http\Requests\Campaign;

use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Foundation\Http\FormRequest;

class CampaignAftermathDoctorRequest extends FormRequest
{
    use AuthorizesCampaignAccess;

    /**
     * Ownership/policy checks run here so they precede validation (matching the old controller order).
     */
    public function authorize(): bool
    {
        $this->ensureAftermathOwner($this, $this->route('aftermath'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attempts' => ['nullable', 'array'],
            // Validate existence here so we can fail fast on bogus IDs rather
            // than silently `continue` past them later — otherwise a player
            // could drain their own scrip on non-resolving attempts.
            'attempts.*.injury_pivot_id' => ['required', 'integer', 'exists:campaign_arsenal_model_injuries,id'],
            // The player makes the doctor flip at the table and picks the result
            // they got from the chart (pg 33); the server applies its outcome.
            'attempts.*.result_id' => ['required', 'integer', 'exists:back_alley_doctor_results,id'],
            'attempts.*.cheated' => ['nullable', 'boolean'],
            // Required when the chosen result adds a new injury (Oops / flip 9
            // RemovedAndReflip) — the injury picked from the chart.
            'attempts.*.added_injury_upgrade_id' => ['nullable', 'integer', 'exists:upgrades,id'],
            // The Lucky Miss result rolled after a red-joker annihilation; or, if
            // that Lucky Miss flip is itself a joker, Doppelganger.
            'attempts.*.lucky_miss_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'attempts.*.lucky_miss_is_joker' => ['nullable', 'boolean'],

        ];
    }
}
