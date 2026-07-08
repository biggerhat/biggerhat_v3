<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Name is intentionally not validated here — usernames are locked
            // after signup, so the profile form never submits it.
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'meta_id' => ['nullable', 'integer', 'exists:metas,id'],
            // Convenience: lets the profile form create a new meta inline.
            // Free-form input — we find-or-create in the controller.
            'meta_name' => ['nullable', 'string', 'max:100'],
            'show_on_supporters_page' => ['sometimes', 'boolean'],
        ];
    }
}
