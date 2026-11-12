<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Foundation\Http\FormRequest;

class TournamentSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Creating needs a signed-in user; editing is gated by the policy in the controller.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            // 1st, 2nd, 3rd... in order; anyone placed lower earns the participation points.
            'points' => ['required', 'array', 'min:1', 'max:16'],
            'points.*' => ['required', 'integer', 'min:0', 'max:1000'],
            'participation_points' => ['required', 'integer', 'min:0', 'max:100'],
            'best_of' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
