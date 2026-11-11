<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use App\Http\Requests\Tournaments\Concerns\ValidatesTeamMembers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentPlayerUpdateRequest extends FormRequest
{
    use ValidatesTeamMembers {
        validateTeamSize as private validateMembersCount;
    }

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
            'display_name' => ['sometimes', 'string', 'max:255'],
            'faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'allegiance_id' => ['sometimes', 'nullable', 'integer', 'exists:tos_allegiances,id'],
            // Empty string / null clears the linked Garrison.
            'garrison_share_code' => ['sometimes', 'nullable', 'string', 'max:24'],
            'is_ringer' => ['sometimes', 'boolean'],
            'checked_in' => ['sometimes', 'boolean'],
            'is_disqualified' => ['sometimes', 'boolean'],
            'dropped_after_round' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'meta_id' => ['sometimes', 'nullable', 'integer', 'exists:metas,id'],
            'meta_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            ...$this->memberRules(requireDeclaration: false),

        ];
    }

    /** @return array<int, callable(\Illuminate\Contracts\Validation\Validator): void> */
    public function after(): array
    {
        return [$this->validateMembersCount()];
    }
}
