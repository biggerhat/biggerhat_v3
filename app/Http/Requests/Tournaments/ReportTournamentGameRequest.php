<?php

namespace App\Http\Requests\Tournaments;

use App\Http\Requests\Tournaments\Concerns\ValidatesRulesetScore;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A player's proposed result for their own game. Who may submit (a linked player in this game,
 * round in progress, self-reporting on) is decided in the controller, which also owns the locking.
 */
class ReportTournamentGameRequest extends FormRequest
{
    use ValidatesRulesetScore;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->scoreRules(),
            // The game wasn't played out; the scores are the outcome both players agree on.
            'agreed' => ['sometimes', 'boolean'],
        ];
    }
}
