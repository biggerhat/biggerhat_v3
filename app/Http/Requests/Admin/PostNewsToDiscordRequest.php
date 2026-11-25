<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PostNewsToDiscordRequest extends FormRequest
{
    /** The route is already behind the manage_news permission. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // A line of the admin's own, shown above the post ("We just shipped…").
            'message' => ['nullable', 'string', 'max:300'],
        ];
    }
}
