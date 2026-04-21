<?php

namespace App\Http\Requests\Games;

use App\Enums\FactionEnum;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitFactionRequest extends FormRequest
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
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
