<?php

namespace App\Http\Requests\Tournaments;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TournamentDetailsRequest extends FormRequest
{
    /** Serialized size cap for the document, in bytes. */
    public const MAX_BYTES = 100_000;

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
        return [
            // null / an empty document clears the details.
            'details' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
                if (is_array($value) && strlen((string) json_encode($value)) > self::MAX_BYTES) {
                    $fail('The event details are too long — trim some text or images.');
                }
            }],
        ];
    }
}
