<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FactionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentRsvpStoreRequest extends FormRequest
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
            'faction' => ['nullable', 'string', Rule::in(array_column(FactionEnum::cases(), 'value'))],

        ];
    }
}
