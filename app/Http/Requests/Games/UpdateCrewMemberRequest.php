<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use App\Models\GameCrewMember;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCrewMemberRequest extends FormRequest
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
        /** @var GameCrewMember $member */
        $member = $this->route('gameCrewMember');

        return [
            'current_health' => ['sometimes', 'integer', 'min:0', 'max:'.$member->max_health],
            'is_activated' => ['sometimes', 'boolean'],
            'attached_tokens' => ['sometimes', 'array'],
            'attached_markers' => ['sometimes', 'array'],
            'attached_upgrades' => ['sometimes', 'array'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'front_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'back_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
