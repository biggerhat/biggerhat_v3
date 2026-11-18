<?php

namespace App\Http\Requests\Games\Fate;

class FateFlipRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'modifier' => ['required', 'integer', 'between:-3,3'],
            'label' => ['nullable', 'string', 'max:60'],
            ...$this->slotRules(),
        ];
    }
}
