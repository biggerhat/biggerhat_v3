<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;

class TournamentGameCrewRequest extends FormRequest
{
    /** Ownership of the build and the link to the pairing are checked in the controller (they need the loaded models). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        // null clears the crew for this round.
        return ['crew_build_id' => ['present', 'nullable', 'integer']];
    }
}
