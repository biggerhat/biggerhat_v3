<?php

namespace App\Http\Requests\TOS;

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyStoreRequest extends FormRequest
{
    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'garrison_id' => ['nullable', 'integer', 'exists:tos_garrisons,id'],
            'format' => ['nullable', Rule::enum(GarrisonFormatEnum::class)],
            // An Envoy is a second, different Allegiance (June 2026 errata).
            'envoy_allegiance_id' => ['nullable', 'integer', 'exists:tos_allegiances,id', 'different:allegiance_id'],
            'notes' => ['nullable', 'string'],

        ];
    }
}
