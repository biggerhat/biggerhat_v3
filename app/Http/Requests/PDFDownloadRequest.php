<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PDFDownloadRequest extends FormRequest
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
            'cards' => 'required|string',
            'options' => 'required|string',

        ];
    }
}
