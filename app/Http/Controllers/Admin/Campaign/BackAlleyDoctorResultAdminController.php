<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreBackAlleyDoctorResultRequest;
use App\Http\Requests\Admin\Campaign\UpdateBackAlleyDoctorResultRequest;
use App\Models\Campaign\BackAlleyDoctorResult;
use Illuminate\Http\Request;

class BackAlleyDoctorResultAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/BackAlleyDoctor/Index', [
            'items' => BackAlleyDoctorResult::orderByRaw('is_black_joker DESC, flip_value_min ASC')
                ->get(['id', 'name', 'flip_value_min', 'flip_value_max', 'is_black_joker', 'is_red_joker', 'outcome_kind']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/BackAlleyDoctor/Form');
    }

    public function edit(Request $request, BackAlleyDoctorResult $doctorResult)
    {
        return inertia('Admin/Campaign/BackAlleyDoctor/Form', ['item' => $doctorResult]);
    }

    public function store(StoreBackAlleyDoctorResultRequest $request)
    {
        $row = BackAlleyDoctorResult::create($request->validated());

        return redirect()->route('admin.campaign.back-alley-doctor.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateBackAlleyDoctorResultRequest $request, BackAlleyDoctorResult $doctorResult)
    {
        $doctorResult->update($request->validated());

        return redirect()->route('admin.campaign.back-alley-doctor.index')->withMessage("{$doctorResult->name} updated.");
    }

    public function delete(Request $request, BackAlleyDoctorResult $doctorResult)
    {
        $name = $doctorResult->name;
        $doctorResult->delete();

        return redirect()->route('admin.campaign.back-alley-doctor.index')->withMessage("{$name} deleted.");
    }
}
