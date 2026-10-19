<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class GamePlayEditTurnScoreRequest extends FormRequest
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
            'strategy_points' => ['required', 'integer', 'min:0', 'max:2'],
            'scheme_points' => ['required', 'integer', 'min:0', 'max:3'],

        ];
    }
}
