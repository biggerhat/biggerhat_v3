<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DeviceTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', 'in:ios,android'],
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
