<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');

        return $game !== null && $this->user()?->can('updateScenario', $game) === true;
    }

    public function rules(): array
    {
        return [
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'deployment' => ['nullable', 'string'],
            'scheme_pool' => ['required', 'array', 'min:3', 'max:3'],
            'scheme_pool.*' => ['integer', 'exists:schemes,id'],
        ];
    }
}
