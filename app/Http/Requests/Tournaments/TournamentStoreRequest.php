<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\EncounterTypeEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\RoleEnum;
use App\Enums\TournamentStatusEnum;
use App\Enums\TournamentTiebreakerEnum;
use App\Http\Requests\Tournaments\Concerns\ValidatesRulesetCustomizations;
use App\Models\Tournament;
use App\Tournaments\Rulesets\Ruleset;
use App\Tournaments\Rulesets\RulesetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TournamentStoreRequest extends FormRequest
{
    use ValidatesRulesetCustomizations;

    public const MAX_OPEN_EVENTS = 5;

    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ruleset' => ['sometimes', Rule::in(app(RulesetRegistry::class)->keys())],
            'description' => ['nullable', 'string'],
            'encounter_size' => ['required', 'integer', 'min:20', 'max:100'],
            'encounter_type' => ['sometimes', Rule::enum(EncounterTypeEnum::class)],
            'planned_rounds' => ['required', 'integer', 'min:1', 'max:7'],
            'season' => ['required', Rule::enum(PoolSeasonEnum::class)],
            'event_date' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'round_time_limit' => ['sometimes', 'integer', 'min:30', 'max:300'],
            // Bye-game scoring overrides; null/absent = use the ruleset's values.
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

    /**
     * Creation is open to everyone signed in, so cap how many unfinished events one person can hold at once.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->validateVariants(), $this->validateTeamSize(), function (Validator $validator): void {
            $user = $this->user();
            if (! $user || $user->hasRole(RoleEnum::SuperAdmin->value)) {
                return;
            }

            $open = Tournament::where('creator_id', $user->id)
                ->where('status', '!=', TournamentStatusEnum::Completed->value)
                ->count();

            if ($open >= self::MAX_OPEN_EVENTS) {
                $validator->errors()->add('name', 'You already have '.self::MAX_OPEN_EVENTS.' unfinished tournaments. Finish or delete one before creating another.');
            }
        }];
    }

    protected function rulesetForCustomizations(): Ruleset
    {
        return app(RulesetRegistry::class)->resolve($this->input('ruleset'));
    }
}
