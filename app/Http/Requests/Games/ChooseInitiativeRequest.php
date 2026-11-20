<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class ChooseInitiativeRequest extends FormRequest
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
            'initiative' => ['required', 'integer', 'in:1,2'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
