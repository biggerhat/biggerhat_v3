<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use App\Http\Requests\Tournaments\Concerns\ValidatesRulesetScore;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScoreTournamentGameRequest extends FormRequest
{
    use ValidatesRulesetScore;

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
            ...$this->scoreRules(),
            'player_one_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_one_master' => ['nullable', 'string', 'max:255'],
            'player_one_title' => ['nullable', 'string', 'max:255'],
            'player_one_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_two_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_two_master' => ['nullable', 'string', 'max:255'],
            'player_two_title' => ['nullable', 'string', 'max:255'],
            'player_two_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            // Agreed result: the game wasn't played to its end; the scores entered are the agreed outcome (TO-approved).
            'agreed' => ['sometimes', 'boolean'],
            // Opt-in override for when the tracker game is still in progress.
            'confirm_override' => ['sometimes', 'boolean'],
        ];
    }
}
