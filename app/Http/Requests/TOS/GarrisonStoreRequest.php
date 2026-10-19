<?php

namespace App\Http\Requests\TOS;

use App\Enums\TOS\GarrisonFormatEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GarrisonStoreRequest extends FormRequest
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
            'format' => ['required', 'string', Rule::in(array_column(GarrisonFormatEnum::cases(), 'value'))],
            'notes' => ['nullable', 'string'],

        ];
    }
}
