<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TriggerRequest;
use App\Models\Trigger;
use Illuminate\Http\Request;

class TriggerAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Triggers/Index', [
            'triggers' => Trigger::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Triggers/TriggerForm', [
            'suits' => SuitEnum::toSelectOptions(),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Trigger $trigger)
    {
        return inertia('Admin/Triggers/TriggerForm', [
            'trigger' => $trigger->loadMissing(['actions']),
            'suits' => SuitEnum::toSelectOptions(),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(TriggerRequest $request)
    {
        $trigger = $this->validateAndSave($request);

        return redirect()->route('admin.triggers.index')->withMessage("{$trigger->name} created successfully.");
    }

    public function update(TriggerRequest $request, Trigger $trigger)
    {
        $trigger = $this->validateAndSave($request, $trigger);

        return redirect()->route('admin.triggers.index')->withMessage("{$trigger->name} has been updated.");
    }

    public function delete(Request $request, Trigger $trigger)
    {
        $name = $trigger->name;
        $trigger->delete();

        return redirect()->route('admin.triggers.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(TriggerRequest $request, ?Trigger $trigger = null): Trigger
    {
        $validated = $request->validated();

        if (! $trigger) {
            // Slug is derived from the name by the UsesSlugName trait on every save.
            $trigger = Trigger::create($validated);
        } else {
            $trigger->update($validated);
        }

        return $trigger;
    }
}
