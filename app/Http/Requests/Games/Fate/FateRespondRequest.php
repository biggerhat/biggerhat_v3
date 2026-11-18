<?php

namespace App\Http\Requests\Games\Fate;

class FateRespondRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'show' => ['sometimes', 'boolean'],
            ...$this->slotRules(),
        ];
    }
}
