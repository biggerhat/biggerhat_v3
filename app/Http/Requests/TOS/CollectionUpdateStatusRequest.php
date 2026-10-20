<?php

namespace App\Http\Requests\TOS;

use Illuminate\Foundation\Http\FormRequest;

class CollectionUpdateStatusRequest extends FormRequest
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
            'unit_sculpt_id' => 'required|exists:tos_unit_sculpts,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',
            // Per-model progress for Squad units (e.g. "6 of 9 painted") —
            // clamped server-side to the unit's Squad size below so a stale
            // client value can't record more than the box actually has.
            'built_count' => 'nullable|integer|min:0',
            'painted_count' => 'nullable|integer|min:0',

        ];
    }
}
