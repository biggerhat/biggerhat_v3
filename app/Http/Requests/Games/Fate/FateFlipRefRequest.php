<?php

namespace App\Http\Requests\Games\Fate;

class FateFlipRefRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'flip' => ['required', 'integer'],
            ...$this->slotRules(),
        ];
    }
}
