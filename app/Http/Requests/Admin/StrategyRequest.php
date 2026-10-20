<?php

namespace App\Http\Requests\Admin;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use App\Models\Token;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StrategyRequest extends FormRequest
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
            'suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'setup' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'scoring' => ['nullable', 'string'],
            'additional_scoring' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'token_ids' => ['nullable', 'array'],
            'token_ids.*' => ['integer', Rule::exists(Token::class, 'id')],
        ];
    }
}
