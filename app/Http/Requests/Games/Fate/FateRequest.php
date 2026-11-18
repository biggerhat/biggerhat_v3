<?php

namespace App\Http\Requests\Games\Fate;

use App\Models\Game;
use App\Support\Fate\FateCards;
use Illuminate\Foundation\Http\FormRequest;

/** A Fate action: only people in the game (or its creator, for a solo game) may act. A solo creator names which slot is acting. */
abstract class FateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');

        return $game !== null && $this->user()?->can('view', $game) === true;
    }

    /** @return array<string, mixed> */
    protected function slotRules(): array
    {
        return ['slot' => ['sometimes', 'integer', 'in:1,2']];
    }

    /** @return list<string> */
    protected function cardRule(): array
    {
        return ['required', 'integer', 'between:0,'.(FateCards::COUNT - 1)];
    }
}
