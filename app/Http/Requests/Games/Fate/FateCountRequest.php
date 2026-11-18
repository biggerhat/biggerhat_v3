<?php

namespace App\Http\Requests\Games\Fate;

class FateCountRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'n' => ['required', 'integer', 'between:1,20'],
            ...$this->slotRules(),
        ];
    }
}
