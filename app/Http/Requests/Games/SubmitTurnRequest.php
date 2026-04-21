<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class SubmitTurnRequest extends FormRequest
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
            'strategy_points' => ['required', 'integer', 'min:0', 'max:2'],
            'scheme_points' => ['required', 'integer', 'min:0', 'max:2'],
            'scheme_action' => ['required', 'string', 'in:scored,held,discarded'],
            'next_scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
            'next_scheme_notes' => ['nullable', 'array'],
            'next_scheme_notes.note' => ['nullable', 'string', 'max:500'],
            'next_scheme_notes.selected_model' => ['nullable', 'string', 'max:255'],
            'next_scheme_notes.selected_marker' => ['nullable', 'string', 'max:255'],
            'next_scheme_notes.terrain_note' => ['nullable', 'string', 'max:255'],
            'identified_scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
