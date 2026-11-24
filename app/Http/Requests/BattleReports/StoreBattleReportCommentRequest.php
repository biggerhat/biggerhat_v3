<?php

namespace App\Http\Requests\BattleReports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A reader comments on a public battle report. */
class StoreBattleReportCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('comment', $this->route('report'));
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:1000']];
    }
}
