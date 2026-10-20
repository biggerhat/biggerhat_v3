<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlogPostStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Blog articles and Site News posts; the category must belong to the matching kind. */
class BlogPostRequest extends FormRequest
{
    /**
     * Route middleware (permission:*) gates access; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Content arrives as a JSON string via FormData — decode it before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('content') && is_string($this->input('content'))) {
            $this->merge(['content' => json_decode($this->input('content'), true)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'excerpt' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'string', Rule::enum(BlogPostStatusEnum::class)],
            'blog_category_id' => ['nullable', 'integer', Rule::exists('blog_categories', 'id')->where('is_news', $this->routeIs('admin.news.*'))],
            'entities' => ['nullable', 'array'],
        ];
    }
}
