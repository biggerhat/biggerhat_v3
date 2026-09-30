<?php

namespace App\Http\Requests\Games;

use App\Enums\GameFormatEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            // Bonanza Brawl uses 11ss; standard uses 35/40/50. Min reduced from 20
            // to 10 to fit Bonanza without rejecting it; max kept generous for
            // homebrew formats (Henchman Hardcore, narrative, etc).
            'encounter_size' => ['required', 'integer', 'min:10', 'max:100'],
            'season' => ['required', 'string'],
            'is_solo' => ['sometimes', 'boolean'],
            'format' => ['sometimes', 'string', Rule::enum(GameFormatEnum::class)],
        ];
    }
}
