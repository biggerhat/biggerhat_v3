<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentScorerStoreRequest extends FormRequest
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
        return ['user_id' => ['required', 'integer', 'exists:users,id']];
    }
}
