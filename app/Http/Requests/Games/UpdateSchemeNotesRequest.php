<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchemeNotesRequest extends FormRequest
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
            'scheme_notes' => ['required', 'array'],
            'scheme_notes.note' => ['nullable', 'string', 'max:500'],
            'scheme_notes.selected_model' => ['nullable', 'string', 'max:255'],
            'scheme_notes.selected_marker' => ['nullable', 'string', 'max:255'],
            'scheme_notes.terrain_note' => ['nullable', 'string', 'max:255'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
