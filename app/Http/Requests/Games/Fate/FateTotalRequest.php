<?php

namespace App\Http\Requests\Games\Fate;

class FateTotalRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'flip' => ['required', 'integer'],
            'total' => ['required', 'integer', 'between:-10,60'],
            ...$this->slotRules(),
        ];
    }
}
