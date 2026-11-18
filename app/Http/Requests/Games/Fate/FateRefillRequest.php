<?php

namespace App\Http\Requests\Games\Fate;

class FateRefillRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'discard' => ['present', 'array', 'max:20'],
            'discard.*' => $this->cardRule(),
            ...$this->slotRules(),
        ];
    }
}
