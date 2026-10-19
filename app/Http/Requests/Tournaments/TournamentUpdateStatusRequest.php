<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentUpdateStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(TournamentStatusEnum::class)],

        ];
    }
}
