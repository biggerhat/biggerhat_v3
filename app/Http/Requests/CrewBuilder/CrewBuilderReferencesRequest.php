<?php

namespace App\Http\Requests\CrewBuilder;

use Illuminate\Foundation\Http\FormRequest;

class CrewBuilderReferencesRequest extends FormRequest
{
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
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ];
    }
}
