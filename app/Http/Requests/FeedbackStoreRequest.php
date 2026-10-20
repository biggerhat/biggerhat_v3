<?php

namespace App\Http\Requests;

use App\Enums\FeedbackCategoryEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedbackStoreRequest extends FormRequest
{
    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'category' => ['required', Rule::enum(FeedbackCategoryEnum::class)],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot — bots typically fill every field. Humans see nothing
            // (the field is hidden via CSS in the Vue template).
            'website' => ['nullable', 'size:0'],

        ];
    }
}
