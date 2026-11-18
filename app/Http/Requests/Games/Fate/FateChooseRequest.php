<?php

namespace App\Http\Requests\Games\Fate;

class FateChooseRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'flip' => ['required', 'integer'],
            'card' => $this->cardRule(),
            'suit' => ['nullable', 'string', 'in:rams,masks,tomes,crows'],
            ...$this->slotRules(),
        ];
    }
}
