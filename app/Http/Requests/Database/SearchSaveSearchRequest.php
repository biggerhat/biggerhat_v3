<?php

namespace App\Http\Requests\Database;

use Illuminate\Foundation\Http\FormRequest;

class SearchSaveSearchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'query_params' => ['required', 'array'],

        ];
    }
}
