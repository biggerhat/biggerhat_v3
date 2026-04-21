<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class SummonCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');

        return $game !== null && $this->user()?->can('view', $game) === true;
    }

    public function rules(): array
    {
        return [
            'character_id' => ['required', 'exists:characters,id'],
            'miniature_id' => ['nullable', 'integer', 'exists:miniatures,id'],
            'is_replacement' => ['sometimes', 'boolean'],
            'replacement_health' => ['nullable', 'integer', 'min:1'],
            'inherited_tokens' => ['nullable', 'array'],
            'inherited_upgrades' => ['nullable', 'array'],
            'is_activated' => ['sometimes', 'boolean'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
