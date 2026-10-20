<?php

namespace App\Http\Requests\Admin;

use App\Enums\SculptVersionEnum;
use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MiniatureRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'character_id' => ['required', 'integer', Rule::exists(Character::class, 'id')],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'version' => ['required', 'string', Rule::enum(SculptVersionEnum::class)],
            'use_existing_back' => ['nullable', 'boolean'],
        ];
    }
}
