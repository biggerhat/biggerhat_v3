<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentPlayerStoreRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            // Validate against the enum — `faction` is cast to FactionEnum on
            // the model, so a non-enum string would crash on read (which is why
            // standings/pairing read getRawOriginal('faction') defensively).
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'is_ringer' => ['sometimes', 'boolean'],
            'meta_id' => ['nullable', 'integer', 'exists:metas,id'],
            // Convenience: lets the registration form create a new meta inline.
            'meta_name' => ['nullable', 'string', 'max:100'],

        ];
    }
}
