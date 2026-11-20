<?php

namespace App\Http\Requests\Games\Deployment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The attacker chooses which of the defender's two groups is deployed first: the one kept for now, or the one held back. */
class ChooseFirstGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['first' => ['required', 'string', Rule::in(['now', 'later'])]];
    }
}
