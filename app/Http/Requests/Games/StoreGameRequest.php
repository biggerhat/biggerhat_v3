<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'encounter_size' => ['required', 'integer', 'min:20', 'max:100'],
            'season' => ['required', 'string'],
            'is_solo' => ['sometimes', 'boolean'],
        ];
    }
}
