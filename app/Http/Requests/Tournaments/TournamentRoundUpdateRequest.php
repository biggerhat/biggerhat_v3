<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\DeploymentEnum;
use App\Enums\TournamentRoundStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentRoundUpdateRequest extends FormRequest
{
    /**
     * Tournament management is authorized in the controller.
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
            'deployment' => ['nullable', Rule::enum(DeploymentEnum::class)],
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'scheme_pool' => ['nullable', 'array', 'min:3', 'max:3'],
            // `distinct` rejects a pool that repeats the same scheme id — TOs were
            // accidentally picking the same scheme twice, which left the spawned
            // game with only 2 distinct picks.
            'scheme_pool.*' => ['integer', 'exists:schemes,id', 'distinct'],
            'status' => ['sometimes', 'string', Rule::enum(TournamentRoundStatusEnum::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheme_pool.*.distinct' => 'A round cannot have duplicate schemes — pick three different schemes.',
        ];
    }
}
