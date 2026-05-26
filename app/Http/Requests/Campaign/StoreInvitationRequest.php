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
            // Either an existing user (id) or an email address.
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:email'],
            'email' => ['nullable', 'email:rfc', 'required_without:user_id'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}
