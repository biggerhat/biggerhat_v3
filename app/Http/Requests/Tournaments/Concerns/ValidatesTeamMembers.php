<?php

namespace App\Http\Requests\Tournaments\Concerns;

use App\Enums\FactionEnum;
use App\Models\Tournament;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Rule;

/**
 * Rules for the people on a team in a team event: a name, an optional linked account, and the same
 * declaration an individual player makes (faction, or allegiance + optional Garrison).
 */
trait ValidatesTeamMembers
{
    protected function tournamentFromRoute(): ?Tournament
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');

        return $tournament;
    }

    /**
     * @param  bool  $requireDeclaration  true when adding a team (everyone must declare); false when editing
     * @return array<string, array<int, mixed>>
     */
    protected function memberRules(bool $requireDeclaration): array
    {
        $declared = $requireDeclaration ? 'required' : 'nullable';
        $declaration = match ($this->tournamentFromRoute()?->rulesetDefinition()->declaration) {
            'allegiance' => [
                'members.*.allegiance_id' => [$declared, 'integer', 'exists:tos_allegiances,id'],
                'members.*.garrison_share_code' => ['nullable', 'string', 'max:24'],
            ],
            'none' => [],
            default => ['members.*.faction' => [$declared, 'string', Rule::enum(FactionEnum::class)]],
        };

        return [
            'members' => ['sometimes', 'array', 'max:4'],
            // Present when editing an existing member (kept in place instead of recreated).
            'members.*.id' => ['sometimes', 'nullable', 'integer'],
            'members.*.display_name' => ['required', 'string', 'max:255'],
            'members.*.user_id' => ['nullable', 'integer', 'exists:users,id', 'distinct'],
            ...$declaration,
        ];
    }

    /**
     * A team event needs a full team (unless it is the ringer, which may have no members at all).
     *
     * @return callable(ValidatorContract): void
     */
    protected function validateTeamSize(): callable
    {
        return function (ValidatorContract $validator): void {
            $tournament = $this->tournamentFromRoute();
            if ($tournament === null) {
                return;
            }

            $members = $this->input('members');
            $count = is_array($members) ? count($members) : 0;

            if (! $tournament->isTeamEvent()) {
                if ($count > 0) {
                    $validator->errors()->add('members', 'This is not a team event.');
                }

                return;
            }

            // On update the flag may not be sent; fall back to the team being edited.
            $isRinger = $this->has('is_ringer')
                ? filter_var($this->input('is_ringer'), FILTER_VALIDATE_BOOLEAN)
                : (bool) $this->route('player')?->is_ringer;
            if (is_array($members) && $count !== (int) $tournament->team_size && ! ($isRinger && $count === 0)) {
                $validator->errors()->add('members', "A team needs exactly {$tournament->team_size} members.");
            }
        };
    }
}
