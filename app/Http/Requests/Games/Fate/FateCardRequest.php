<?php

namespace App\Http\Requests\Games\Fate;

class FateCardRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'card' => $this->cardRule(),
            ...$this->slotRules(),
        ];
    }
}
