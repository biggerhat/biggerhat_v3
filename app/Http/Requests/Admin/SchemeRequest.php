<?php

namespace App\Http\Requests\Admin;

use App\Enums\PoolSeasonEnum;
use App\Models\Scheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchemeRequest extends FormRequest
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
            'season' => ['required', 'string', Rule::enum(PoolSeasonEnum::class)],
            'selector' => ['nullable', 'string'],
            'prerequisite' => ['nullable', 'string'],
            'reveal' => ['nullable', 'string'],
            'scoring' => ['nullable', 'string'],
            'additional' => ['nullable', 'string'],
            'requirements' => ['nullable', 'array'],
            'requirements.*.type' => ['required', 'string', 'in:select_model,select_marker,terrain_note'],
            'requirements.*.allegiance' => ['nullable', 'string', 'in:enemy,friendly'],
            'requirements.*.unique' => ['nullable', 'boolean'],
            'requirements.*.cost_operator' => ['nullable', 'string', 'in:>,<,>=,<=,none'],
            'requirements.*.cost_value' => ['nullable', 'integer', 'min:0', 'max:20'],
            'image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'next_scheme_one_id' => ['nullable', 'integer', Rule::exists(Scheme::class, 'id')],
            'next_scheme_two_id' => ['nullable', 'integer', Rule::exists(Scheme::class, 'id')],
            'next_scheme_three_id' => ['nullable', 'integer', Rule::exists(Scheme::class, 'id')],
            'next_scheme_four_id' => ['nullable', 'integer', Rule::exists(Scheme::class, 'id')],
        ];
    }
}
