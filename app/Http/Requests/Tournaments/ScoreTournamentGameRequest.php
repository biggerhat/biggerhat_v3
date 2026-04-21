<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScoreTournamentGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');

        return $tournament !== null && $this->user()?->can('manage', $tournament) === true;
    }

    public function rules(): array
    {
        $validFactions = collect(FactionEnum::cases())->pluck('value')->all();

        return [
            'player_one_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_one_master' => ['nullable', 'string', 'max:255'],
            'player_one_title' => ['nullable', 'string', 'max:255'],
            'player_one_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_one_strategy_vp' => ['required', 'integer', 'min:0', 'max:5'],
            'player_one_scheme_vp' => ['required', 'integer', 'min:0', 'max:6'],
            'player_two_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_two_master' => ['nullable', 'string', 'max:255'],
            'player_two_title' => ['nullable', 'string', 'max:255'],
            'player_two_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_two_strategy_vp' => ['required', 'integer', 'min:0', 'max:5'],
            'player_two_scheme_vp' => ['required', 'integer', 'min:0', 'max:6'],
            // Opt-in override for when the tracker game is still in progress.
            'confirm_override' => ['sometimes', 'boolean'],
        ];
    }
}
