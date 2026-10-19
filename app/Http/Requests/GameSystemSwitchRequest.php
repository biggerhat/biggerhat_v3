<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameSystemSwitchRequest extends FormRequest
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
            'system' => ['required', 'string', 'in:malifaux,tos'],
        ];
    }
}
