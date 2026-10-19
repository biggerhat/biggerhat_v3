<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class GamePlayBulkAttachTokenRequest extends FormRequest
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
            'token_id' => ['required', 'integer', 'exists:tokens,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],

        ];
    }
}
