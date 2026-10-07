<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:info,warning,success'],
            'audience' => ['required', 'in:all,authenticated,super_admin'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_dismissable' => ['boolean'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:64'],
        ];
    }
}
