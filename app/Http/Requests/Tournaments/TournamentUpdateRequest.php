<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\EncounterTypeEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentTiebreakerEnum;
use App\Http\Requests\Tournaments\Concerns\ValidatesRulesetCustomizations;
use App\Models\Tournament;
use App\Tournaments\Rulesets\Ruleset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentUpdateRequest extends FormRequest
{
    use ValidatesRulesetCustomizations;

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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'encounter_size' => ['sometimes', 'integer', 'min:20', 'max:100'],
            'encounter_type' => ['sometimes', Rule::enum(EncounterTypeEnum::class)],
            'planned_rounds' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'season' => ['sometimes', Rule::enum(PoolSeasonEnum::class)],
            'event_date' => ['sometimes', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'round_time_limit' => ['sometimes', 'integer', 'min:30', 'max:300'],
            'bye_tp' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
            'bye_diff' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'bye_vp' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'tiebreaker_mode' => ['sometimes', Rule::enum(TournamentTiebreakerEnum::class)],
            'allow_self_report' => ['sometimes', 'boolean'],
            // Capacity: null = unlimited; RSVPs past it are waitlisted. Registration can also close at a set time.
            'max_players' => ['sometimes', 'nullable', 'integer', 'min:2', 'max:512'],
            'registration_closes_at' => ['sometimes', 'nullable', 'date'],
            'instant_signup' => ['sometimes', 'boolean'],
            // Players attach a Crew Builder list before the event starts (faction-declaring rulesets, individual play).
            'require_crew_lists' => ['sometimes', 'boolean'],
            // 1 = individuals; 2–4 = teams of that size (the ruleset must support teams).
            'team_size' => ['sometimes', 'integer', 'min:1', 'max:4'],
            ...$this->customizationRules(),

        ];
    }

    /** @return array<int, callable(\Illuminate\Contracts\Validation\Validator): void> */
    public function after(): array
    {
        return [$this->validateVariants(), $this->validateTeamSize()];
    }

    protected function rulesetForCustomizations(): Ruleset
    {
        /** @var Tournament $tournament */
        $tournament = $this->route('tournament');

        return $tournament->rulesetDefinition();
    }
}
