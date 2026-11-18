<?php

namespace App\Http\Requests\Games\Fate;

class FateArrangeRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1', 'max:20'],
            'decisions.*.card' => $this->cardRule(),
            'decisions.*.to' => ['required', 'string', 'in:top,bottom,discard,hand'],
            ...$this->slotRules(),
        ];
    }
}
