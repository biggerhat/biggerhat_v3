<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class SkipCrewRequest extends FormRequest
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
            // Solo games name the slot being skipped; without one the solo opponent is skipped (the original behaviour).
            // In a 2-player game the caller always skips their own crew.
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
