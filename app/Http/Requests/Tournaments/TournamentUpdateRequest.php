<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\EncounterTypeEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentTiebreakerEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentUpdateRequest extends FormRequest
{
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
            'bye_tp' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'bye_diff' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'bye_vp' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'tiebreaker_mode' => ['sometimes', Rule::enum(TournamentTiebreakerEnum::class)],

        ];
    }
}
