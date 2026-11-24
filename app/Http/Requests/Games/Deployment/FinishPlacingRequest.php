<?php

namespace App\Http\Requests\Games\Deployment;

use Illuminate\Foundation\Http\FormRequest;

/** The player who is placing says they are finished, with where each model of the group stands. */
class FinishPlacingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'positions' => ['required', 'array', 'min:1', 'max:60'],
            'positions.*.x' => ['required', 'numeric', 'between:0,36'],
            'positions.*.y' => ['required', 'numeric', 'between:0,36'],
        ];
    }
}
