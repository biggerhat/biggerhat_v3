<?php

namespace App\Http\Requests\Admin;

use App\Enums\GameModeTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriggerRequest extends FormRequest
{
    /**
     * Route middleware (permission:edit_*) gates access; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'suits' => ['nullable', 'string', 'max:255'],
            'stone_cost' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            // Optional — defaults to 'standard' on the column when not sent.
            'game_mode_type' => ['sometimes', 'string', Rule::enum(GameModeTypeEnum::class)],
        ];
    }
}
