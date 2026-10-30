<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\EncounterTypeEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\RoleEnum;
use App\Enums\TournamentStatusEnum;
use App\Enums\TournamentTiebreakerEnum;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TournamentStoreRequest extends FormRequest
{
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
            'description' => ['nullable', 'string'],
            'encounter_size' => ['required', 'integer', 'min:20', 'max:100'],
            'encounter_type' => ['sometimes', Rule::enum(EncounterTypeEnum::class)],
            'planned_rounds' => ['required', 'integer', 'min:1', 'max:7'],
            'season' => ['required', Rule::enum(PoolSeasonEnum::class)],
            'event_date' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'round_time_limit' => ['sometimes', 'integer', 'min:30', 'max:300'],
            // Bye-game scoring (defaults to Gaining Grounds 3 / +4 / 6).
            'bye_tp' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'bye_diff' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'bye_vp' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'tiebreaker_mode' => ['sometimes', Rule::enum(TournamentTiebreakerEnum::class)],

        ];
    }

    /**
     * Creation is open to everyone signed in, so cap how many unfinished events one person can hold at once.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
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
}
