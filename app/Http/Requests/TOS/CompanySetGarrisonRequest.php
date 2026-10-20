<?php

namespace App\Http\Requests\TOS;

use Illuminate\Foundation\Http\FormRequest;

class CompanySetGarrisonRequest extends FormRequest
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
            'garrison_id' => ['nullable', 'integer', 'exists:tos_garrisons,id'],

        ];
    }
}
