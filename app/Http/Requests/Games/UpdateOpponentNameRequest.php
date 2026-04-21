<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOpponentNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');

        return $game !== null
            && $game->is_solo
            && $this->user()?->can('update', $game) === true;
    }

    public function rules(): array
    {
        return [
            'opponent_name' => ['required', 'string', 'max:255'],
        ];
    }
}
