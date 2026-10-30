<?php

namespace App\Http\Requests\Admin;

use App\Enums\FeedbackStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkFeedbackRequest extends FormRequest
{
    /**
     * Route middleware (permission:manage_feedback) gates access; this request only validates.
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'exists:feedback,id'],
            'action' => ['required', Rule::in([...array_map(fn (FeedbackStatusEnum $s) => $s->value, FeedbackStatusEnum::cases()), 'delete'])],
        ];
    }
}
