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
            ...$this->scoreRules(),
            'player_one_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_one_master' => ['nullable', 'string', 'max:255'],
            'player_one_title' => ['nullable', 'string', 'max:255'],
            'player_one_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_two_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_two_master' => ['nullable', 'string', 'max:255'],
            'player_two_title' => ['nullable', 'string', 'max:255'],
            'player_two_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            // Opt-in override for when the tracker game is still in progress.
            'confirm_override' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Score components come from the tournament's ruleset (e.g. strategy + scheme VP for Malifaux,
     * a single VP total for Fields of Glory): `player_one_score.<key>` / `player_two_score.<key>`.
     *
     * @return array<string, array<int, mixed>>
     */
    private function scoreRules(): array
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');
        $fields = $tournament?->rulesetDefinition()->scoreFields ?? [];

        $rules = [];
        foreach (['player_one_score', 'player_two_score'] as $side) {
            $rules[$side] = ['required', 'array'];
            foreach ($fields as $field) {
                $rules["{$side}.{$field['key']}"] = ['required', 'integer', 'min:'.$field['min'], 'max:'.$field['max']];
            }
        }

        return $rules;
    }
}
