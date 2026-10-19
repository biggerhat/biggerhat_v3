<?php

namespace App\Http\Requests\Campaign;

use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Foundation\Http\FormRequest;

class CampaignAftermathUpdateStoryRequest extends FormRequest
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
            'story_entry' => ['nullable', 'string', 'max:5000'],

        ];
    }
}
