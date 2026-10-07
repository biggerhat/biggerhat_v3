<?php

namespace App\Http\Requests\Admin;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\GameModeTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbilityRequest extends FormRequest
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
            'game_mode_type' => ['required', 'string', Rule::enum(GameModeTypeEnum::class)],
            'suits' => ['nullable', 'string', 'max:255'],
            'defensive_ability_type' => ['nullable', 'string', Rule::enum(DefensiveAbilityTypeEnum::class)],
            'costs_stone' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string'],
        ];
    }
}
