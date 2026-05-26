<?php

namespace App\Http\Requests\Campaign;

use App\Support\CampaignAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CampaignAccess::canUse($this->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Rulebook recommends 4–12 (pg 15). Allow 2–26 so groups can run
            // short test runs or extended sagas; we trust the organizer.
            'length_weeks' => ['required', 'integer', 'min:2', 'max:26'],
            'competitive' => ['required', 'boolean'],
            'weekly_event_active' => ['required', 'boolean'],
            // Solo mode lets one user track their own crew end-to-end without
            // inviting opponents. Set at create time and immutable after.
            'is_solo' => ['sometimes', 'boolean'],
            // Map of toggle → bool. Unknown keys silently ignored — keeps the
            // server tolerant of UI feature flags rolling forward.
            'optional_rules' => ['nullable', 'array'],
            'optional_rules.*' => ['boolean'],
        ];
    }
}
