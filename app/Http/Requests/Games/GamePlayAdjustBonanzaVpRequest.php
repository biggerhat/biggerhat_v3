<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class GamePlayAdjustBonanzaVpRequest extends FormRequest
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
            'slot' => ['sometimes', 'integer', 'in:1,2'],
            'delta' => ['required', 'integer', 'min:-20', 'max:20'],

        ];
    }
}
