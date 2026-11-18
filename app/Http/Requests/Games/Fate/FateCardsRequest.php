<?php

namespace App\Http\Requests\Games\Fate;

class FateCardsRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'cards' => ['required', 'array', 'min:1', 'max:20'],
            'cards.*' => $this->cardRule(),
            ...$this->slotRules(),
        ];
    }
}
