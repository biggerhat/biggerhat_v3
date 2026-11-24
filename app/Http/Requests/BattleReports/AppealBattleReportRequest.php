<?php

namespace App\Http\Requests\BattleReports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** A writer asks the moderators to look again at a report that was turned down. */
class AppealBattleReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('appeal', $this->route('report'));
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:3', 'max:500']];
    }
}
