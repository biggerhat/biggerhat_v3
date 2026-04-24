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
            'triggers' => Trigger::with('actions:id,name')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'suits', 'margin_cost', 'timing']),
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
        $trigger->load('actions:id');

        return inertia('Admin/TOS/Triggers/TriggerForm', [
            'trigger' => $trigger,
            'actions' => fn () => Action::orderBy('name')->get(['id', 'name']),
            'timings' => fn () => TriggerTimingEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreTriggerRequest $request)
    {
        $data = $request->validated();
        $actionIds = $data['action_ids'] ?? [];
        unset($data['action_ids']);

        $trigger = Trigger::create($data);
        $trigger->actions()->sync($this->withSortOrder($actionIds));

        return redirect()->route('admin.tos.triggers.index')->withMessage("{$trigger->name} created.");
    }

    public function update(UpdateTriggerRequest $request, Trigger $trigger)
    {
        $data = $request->validated();
        $actionIds = $data['action_ids'] ?? [];
        unset($data['action_ids']);

        $trigger->update($data);
        $trigger->actions()->sync($this->withSortOrder($actionIds));

        return redirect()->route('admin.tos.triggers.index')->withMessage("{$trigger->name} updated.");
    }

    public function delete(Request $request, Trigger $trigger)
    {
        $name = $trigger->name;
        $trigger->delete();

        return redirect()->route('admin.tos.triggers.index')->withMessage("{$name} deleted.");
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{sort_order: int}>
     */
    private function withSortOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }
}
