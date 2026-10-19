<?php

namespace App\Http\Requests\Campaign;

use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Foundation\Http\FormRequest;

class CampaignAftermathDrawHandRequest extends FormRequest
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
            'completed_without_withdrawing' => ['required', 'boolean'],
            'schemes_completed' => ['required', 'integer', 'min:0', 'max:3'],

        ];
    }
}
