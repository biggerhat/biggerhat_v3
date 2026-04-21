<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use App\Models\GameCrewMember;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');
        /** @var GameCrewMember|null $member */
        $member = $this->route('gameCrewMember');

        return $game !== null
            && $member !== null
            && $this->user()?->can('updateCrewMember', [$game, $member]) === true;
    }

    public function rules(): array
    {
        return [
            'character_id' => ['required', 'exists:characters,id'],
            'miniature_id' => ['nullable', 'integer', 'exists:miniatures,id'],
        ];
    }
}
