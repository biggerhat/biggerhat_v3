<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

/** Swapping Attacker/Defender is a solo-game convenience: only the game's creator, and only while the game is being set up. */
class SwapRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');

        return $game !== null && $game->is_solo && $game->creator_id === $this->user()?->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
