<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use App\Http\Requests\Tournaments\Concerns\ValidatesTeamMembers;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentPlayerStoreRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            // A team event declares per member (below); an individual event declares on the player.
            ...($this->tournamentFromRoute()?->isTeamEvent() ? $this->teamRules() : $this->declarationRules()),
            'is_ringer' => ['sometimes', 'boolean'],
            'meta_id' => ['nullable', 'integer', 'exists:metas,id'],
            // Convenience: lets the registration form create a new meta inline.
            'meta_name' => ['nullable', 'string', 'max:100'],

        ];
    }

    /**
     * Team events: the team (this row) is named by `display_name`; the people on it are `members`,
     * each declaring for themselves. A ringer team may have no members.
     *
     * @return array<string, array<int, mixed>>
     */
    private function teamRules(): array
    {
        $ringer = filter_var($this->input('is_ringer'), FILTER_VALIDATE_BOOLEAN);

        return [
            ...$this->memberRules(requireDeclaration: true),
            'members' => [$ringer ? 'sometimes' : 'required', 'array', 'max:4'],
        ];
    }

    /** @return array<int, callable(\Illuminate\Contracts\Validation\Validator): void> */
    public function after(): array
    {
        return [$this->validateMembersCount()];
    }

    /**
     * What the player declares depends on the ruleset: a Malifaux faction, or a TOS Allegiance
     * (plus an optional Garrison share code). `faction` is cast to FactionEnum on the model, so a
     * non-enum string would crash on read (standings/pairing read getRawOriginal('faction') defensively).
     *
     * @return array<string, array<int, mixed>>
     */
    private function declarationRules(): array
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');

        return match ($tournament?->rulesetDefinition()->declaration) {
            'allegiance' => [
                'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
                'garrison_share_code' => ['nullable', 'string', 'max:24'],
            ],
            'none' => [],
            default => ['faction' => ['required', 'string', Rule::enum(FactionEnum::class)]],
        };
    }
}
