<?php

namespace App\Http\Requests\Admin;

use App\Enums\BaseSizeEnum;
use App\Models\Character;
use App\Models\Upgrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkerRequest extends FormRequest
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
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'description' => ['nullable', 'string'],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', Rule::exists(Character::class, 'slug')],
            'upgrades' => ['nullable', 'array'],
            'upgrades.*' => ['string', Rule::exists(Upgrade::class, 'slug')],
        ];
    }
}
