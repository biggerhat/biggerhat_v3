<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign
            && $this->user()
            && $this->user()->can('update', $campaign);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'length_weeks' => ['sometimes', 'required', 'integer', 'min:2', 'max:26'],
            'competitive' => ['sometimes', 'required', 'boolean'],
            'weekly_event_active' => ['sometimes', 'required', 'boolean'],
            'optional_rules' => ['nullable', 'array'],
            'optional_rules.*' => ['boolean'],
        ];
    }
}
