<?php

namespace App\Http\Requests\Tournaments;

use App\Services\TournamentCutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentCutStartRequest extends FormRequest
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
        return ['size' => ['required', 'integer', Rule::in(TournamentCutService::SIZES)]];
    }
}
