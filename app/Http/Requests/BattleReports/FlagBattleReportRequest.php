<?php

namespace App\Http\Requests\BattleReports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A reader reports a public battle report to the moderators. */
class FlagBattleReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('flag', $this->route('report'));
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:300']];
    }
}
