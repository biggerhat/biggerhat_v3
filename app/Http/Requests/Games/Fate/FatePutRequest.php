<?php

namespace App\Http\Requests\Games\Fate;

class FatePutRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'card' => $this->cardRule(),
            'where' => ['required', 'string', 'in:top,bottom'],
            ...$this->slotRules(),
        ];
    }
}
