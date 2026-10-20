<?php

namespace App\Http\Requests\Admin;

use App\Enums\LoreMediaTypeEnum;
use App\Models\Character;
use App\Models\LoreMedia;
use App\Models\TOS\Unit as TosUnit;
use App\Rules\SingleMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoreRequest extends FormRequest
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
            'file' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg,png,webp,pdf'],
            'remove_file' => ['nullable', 'boolean'],
            'lore_media' => ['nullable', 'array'],
            'lore_media.*' => ['string', new SingleMatch(LoreMedia::class, 'name')],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', new SingleMatch(Character::class, 'display_name')],
            'tos_units' => ['nullable', 'array'],
            'tos_units.*' => ['string', Rule::exists(TosUnit::class, 'slug')],
            // Inline new media creation (array of objects)
            'new_media' => ['nullable', 'array'],
            'new_media.*.name' => ['required_with:new_media', 'string', 'max:255'],
            'new_media.*.type' => ['required_with:new_media', 'string', Rule::enum(LoreMediaTypeEnum::class)],
            'new_media.*.link' => ['nullable', 'string', 'max:500'],
        ];
    }
}
