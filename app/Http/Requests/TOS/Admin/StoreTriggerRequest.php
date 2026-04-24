<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\TriggerTimingEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_trigger') ?? false;
    }

    public function rules(): array
    {
        return [
            // Triggers attach to one or more Actions — shared triggers (e.g.
            // "Critical") appear on every Melee action without duplication.
            'action_ids' => ['required', 'array', 'min:1'],
            'action_ids.*' => ['integer', 'exists:tos_actions,id'],
            'name' => ['required', 'string', 'max:255'],
            'suits' => ['nullable', 'string', 'max:32', 'prohibits:margin_cost'],
            'margin_cost' => ['nullable', 'integer', 'min:0', 'max:255', 'prohibits:suits'],
            'timing' => ['nullable', 'string', Rule::enum(TriggerTimingEnum::class)],
            'body' => ['nullable', 'string'],
        ];
    }
}
