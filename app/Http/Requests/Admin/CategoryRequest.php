<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Blog and News categories share one shape; the controllers decide `is_news`. */
class CategoryRequest extends FormRequest
{
    /**
     * Route middleware (permission:*) gates access; this request only validates.
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
            'description' => ['nullable', 'string'],
        ];
    }
}
