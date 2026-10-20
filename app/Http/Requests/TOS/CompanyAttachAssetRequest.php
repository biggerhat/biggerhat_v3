<?php

namespace App\Http\Requests\TOS;

use Illuminate\Foundation\Http\FormRequest;

class CompanyAttachAssetRequest extends FormRequest
{
    /**
     * Ownership/policy checks run here so they precede validation (matching the old controller order).
     */
    public function authorize(): bool
    {
        abort_unless($this->route('companyUnit')->company_id === $this->route('company')->id, 404);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:tos_assets,id'],

        ];
    }
}
