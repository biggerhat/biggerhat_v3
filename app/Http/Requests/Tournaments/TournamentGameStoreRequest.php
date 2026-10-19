<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentGameStoreRequest extends FormRequest
{
    /**
     * Ownership/policy checks run here so they precede validation (matching the old controller order).
     */
    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('tournament'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'player_one_id' => ['required', 'exists:tournament_players,id'],
            'player_two_id' => ['nullable', 'exists:tournament_players,id'],
            'is_bye' => ['sometimes', 'boolean'],
            'table_number' => ['nullable', 'integer', 'min:1'],

        ];
    }
}
