<?php

namespace App\Http\Requests\TOS;

use Illuminate\Foundation\Http\FormRequest;

class CollectionUpdateAssetStatusRequest extends FormRequest
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
            'asset_id' => 'required|exists:tos_assets,id',
            'is_built' => 'nullable|boolean',
            'is_painted' => 'nullable|boolean',

        ];
    }
}
