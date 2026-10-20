<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class GamePlayRestoreTokensRequest extends FormRequest
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
            'tokens' => ['required', 'array', 'min:1'],
            'tokens.*.member_id' => ['required', 'integer'],
            'tokens.*.token_id' => ['required', 'integer'],
            'tokens.*.token_name' => ['nullable', 'string'],

        ];
    }
}
