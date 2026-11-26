<?php

namespace App\Http\Requests\Games\Fate;

use App\Support\Fate\FateCards;

class FateRefillRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'discard' => ['present', 'array', 'max:20'],
            'discard.*' => $this->cardRule(),
            // a lower hand limit for this refill (a master or effect that shrinks the hand), and shuffling the discard pile in first
            'max' => ['nullable', 'integer', 'min:1', 'max:'.FateCards::HAND_MAX],
            'shuffle' => ['nullable', 'boolean'],
            ...$this->slotRules(),
        ];
    }
}
