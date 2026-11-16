<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /**
     * Positions are inches from the table corner on a 36" table. Which models the caller may move is checked in the controller.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'positions' => ['required', 'array', 'min:1', 'max:60'],
            'positions.*.x' => ['required', 'numeric', 'between:0,36'],
            'positions.*.y' => ['required', 'numeric', 'between:0,36'],
        ];
    }
}
