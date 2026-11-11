<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentGameSwapTablesRequest extends FormRequest
{
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
            'game_id' => ['required', 'integer', 'exists:tournament_games,id'],
            'other_game_id' => ['required', 'integer', 'different:game_id', 'exists:tournament_games,id'],
        ];
    }
}
