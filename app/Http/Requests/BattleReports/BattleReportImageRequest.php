<?php

namespace App\Http\Requests\BattleReports;

use Illuminate\Foundation\Http\FormRequest;

class BattleReportImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']];
    }
}
