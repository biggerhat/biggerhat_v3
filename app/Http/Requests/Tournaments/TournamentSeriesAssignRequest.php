<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentSeriesAssignRequest extends FormRequest
{
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
        // null takes the event out of its series.
        return ['series' => ['nullable', 'uuid', 'exists:tournament_series,uuid']];
    }
}
