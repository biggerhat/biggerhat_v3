<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentAwardRequest extends FormRequest
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
        $tournament = $this->route('tournament');

        return [
            'name' => ['required', 'string', 'max:80'],
            // null leaves the award unassigned (organizer-only until someone gets it).
            'player_id' => ['nullable', 'integer', Rule::exists('tournament_players', 'id')->where('tournament_id', $tournament->id)],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
