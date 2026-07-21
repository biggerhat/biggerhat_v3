<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign\Campaign;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        // Solo campaigns are intentionally single-user; refuse before
        // organizer-check so the 403 surfaces consistently in either order.
        if ($campaign instanceof Campaign && $campaign->is_solo) {
            return false;
        }

        return $campaign instanceof Campaign
            && $this->user()
            && $this->user()->can('update', $campaign);
    }

    public function rules(): array
    {
        return [
            // One or more existing users (bulk-select), a single legacy
            // user_id, and/or a single email address — at least one of the
            // three must be present, enforced in withValidator() since no
            // single required_without covers a three-way either/or.
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email:rfc'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->input('user_ids')) && empty($this->input('user_id')) && empty($this->input('email'))) {
                $validator->errors()->add('user_ids', 'Select at least one player or enter an email.');
            }
        });
    }
}
