<?php

namespace App\Http\Requests\BattleReports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DismissBattleReportFlagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('moderate', $this->route('report'));
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [];
    }
}
