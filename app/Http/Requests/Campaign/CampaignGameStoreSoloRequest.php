<?php

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;

class CampaignGameStoreSoloRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            // Player's own scoring + scheme/strategy progress.
            'vp_self' => ['required', 'integer', 'min:0', 'max:20'],
            'schemes_completed' => ['required', 'integer', 'min:0', 'max:3'],
            // Opponent VP is optional (some scenarios are co-op-like). Default 0.
            'vp_opponent' => ['nullable', 'integer', 'min:0', 'max:20'],
            'won' => ['required', 'boolean'],
            'withdrew' => ['sometimes', 'boolean'],
            // Turn is only meaningful when the player withdrew; reject the
            // inconsistent combo so downstream consumers can trust the row.
            'withdrew_turn' => ['nullable', 'required_if:withdrew,true', 'integer', 'min:1', 'max:10'],

        ];
    }
}
