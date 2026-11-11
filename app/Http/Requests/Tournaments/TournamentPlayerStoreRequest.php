<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentPlayerStoreRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            ...$this->declarationRules(),
            'is_ringer' => ['sometimes', 'boolean'],
            'meta_id' => ['nullable', 'integer', 'exists:metas,id'],
            // Convenience: lets the registration form create a new meta inline.
            'meta_name' => ['nullable', 'string', 'max:100'],

        ];
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
