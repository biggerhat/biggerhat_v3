<?php

namespace App\Http\Requests\Admin;

use App\Enums\FeedbackStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedbackRequest extends FormRequest
{
    /**
     * Route middleware (role:/permission:) gates access; this request only validates.
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
            'status' => ['sometimes', Rule::enum(FeedbackStatusEnum::class)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
