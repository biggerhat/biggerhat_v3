<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Swap who sits in one pairing slot during round setup: with another pairing's slot, or with an unpaired player.
 */
class TournamentGameSwapRequest extends FormRequest
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
        $slots = Rule::in(['player_one', 'player_two']);

        return [
            'game_id' => ['required', 'integer', 'exists:tournament_games,id'],
            'slot' => ['required', $slots],
            'other_game_id' => ['required_without:player_id', 'nullable', 'integer', 'exists:tournament_games,id'],
            'other_slot' => ['required_with:other_game_id', 'nullable', $slots],
            'player_id' => ['required_without:other_game_id', 'nullable', 'integer', 'exists:tournament_players,id'],
        ];
    }
}
