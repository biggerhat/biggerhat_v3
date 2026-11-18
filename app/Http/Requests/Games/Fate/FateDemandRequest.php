<?php

namespace App\Http\Requests\Games\Fate;

class FateDemandRequest extends FateRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'in:discard,reveal'],
            'count' => ['required_if:kind,discard', 'nullable', 'integer', 'between:1,7'],
            'note' => ['nullable', 'string', 'max:80'],
            ...$this->slotRules(),
        ];
    }
}
