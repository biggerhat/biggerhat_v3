<?php

namespace App\Http\Requests\Tournaments\Concerns;

use App\Models\Tournament;

/**
 * Score-component rules shared by organizer score entry and player self-reporting: the components
 * (e.g. strategy + scheme VP for Malifaux, a single VP total for Fields of Glory) come from the
 * tournament's ruleset as `player_one_score.<key>` / `player_two_score.<key>`.
 */
trait ValidatesRulesetScore
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function scoreRules(): array
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
