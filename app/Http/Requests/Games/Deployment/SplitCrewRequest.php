<?php

namespace App\Http\Requests\Games\Deployment;

use Illuminate\Foundation\Http\FormRequest;

/** The defender divides their crew in two: the models listed are deployed second (an empty list keeps the crew together). */
class SplitCrewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'later' => ['present', 'array', 'max:60'],
            'later.*' => ['integer', 'distinct'],
        ];
    }
}
