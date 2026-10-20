<?php

namespace App\Http\Requests\Admin;

use App\Enums\TournamentRoundStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForceRoundStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(TournamentRoundStatusEnum::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
