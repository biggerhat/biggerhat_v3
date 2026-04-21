<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCrewRequest extends FormRequest
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
            'crew_build_id' => ['required', 'exists:crew_builds,id'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
