<?php

namespace App\Http\Requests\Campaign;

use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Foundation\Http\FormRequest;

class CampaignAftermathBarterRequest extends FormRequest
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
            'purchases' => ['nullable', 'array'],
            // Equipment lives on `upgrades` post-consolidation. The `exists`
            // rule requires the row to be campaign-mode equipment specifically.
            'purchases.*' => ['integer', 'exists:upgrades,id'],
            // Red joker on the barter flip → one Those Who Thirst item (pg 21, 29-30), picked from its own sub-table.
            'ttw_pickup' => ['nullable', 'integer', 'exists:upgrades,id'],

        ];
    }
}
