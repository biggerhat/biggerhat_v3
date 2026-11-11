<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('tournament'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']];
    }
}
