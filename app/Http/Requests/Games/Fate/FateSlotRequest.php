<?php

namespace App\Http\Requests\Games\Fate;

class FateSlotRequest extends FateRequest
{
    public function rules(): array
    {
        return [

            ...$this->slotRules(),
        ];
    }
}
