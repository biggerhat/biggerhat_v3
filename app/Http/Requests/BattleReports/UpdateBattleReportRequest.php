<?php

namespace App\Http\Requests\BattleReports;

use App\Models\BattleReport;
use Illuminate\Support\Facades\Gate;

class UpdateBattleReportRequest extends StoreBattleReportRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('report'));
    }

    protected function currentReport(): ?BattleReport
    {
        return $this->route('report');
    }
}
