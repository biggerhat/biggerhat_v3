<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\TriggerTimingEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreTriggerRequest;
use App\Http\Requests\TOS\Admin\UpdateTriggerRequest;
use App\Models\TOS\Action;
use App\Models\TOS\Trigger;
use Illuminate\Http\Request;

class TriggerAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/TOS/Triggers/Index', [
            'triggers' => Trigger::with('action:id,name')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'action_id', 'suits', 'margin_cost', 'timing', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Triggers/TriggerForm', [
            'actions' => fn () => Action::orderBy('name')->get(['id', 'name']),
            'timings' => fn () => TriggerTimingEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Trigger $trigger)
    {
        return inertia('Admin/TOS/Triggers/TriggerForm', [
            'trigger' => $trigger,
            'actions' => fn () => Action::orderBy('name')->get(['id', 'name']),
            'timings' => fn () => TriggerTimingEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreTriggerRequest $request)
    {
        $trigger = Trigger::create($request->validated());

        return redirect()->route('admin.tos.triggers.index')->withMessage("{$trigger->name} created.");
    }

    public function update(UpdateTriggerRequest $request, Trigger $trigger)
    {
        $trigger->update($request->validated());

        return redirect()->route('admin.tos.triggers.index')->withMessage("{$trigger->name} updated.");
    }

    public function delete(Request $request, Trigger $trigger)
    {
        $name = $trigger->name;
        $trigger->delete();

        return redirect()->route('admin.tos.triggers.index')->withMessage("{$name} deleted.");
    }
}
