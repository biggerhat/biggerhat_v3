<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\ModifierTypeEnum;
use App\Enums\ResistanceTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Character;
use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActionAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Actions/Index', [
            'actions' => Action::all(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Actions/ActionForm', [
            'action_types' => ActionTypeEnum::toSelectOptions(),
            'range_types' => ActionRangeTypeEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'resistance_types' => ResistanceTypeEnum::toSelectOptions(),
            'modifier_types' => ModifierTypeEnum::toSelectOptions(),
            'triggers' => Trigger::toSelectOptions('name', 'slug'),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function edit(Request $request, Action $action)
    {
        return inertia('Admin/Actions/ActionForm', [
            'action' => $action->loadMissing(['triggers', 'characters']),
            'action_types' => ActionTypeEnum::toSelectOptions(),
            'range_types' => ActionRangeTypeEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'resistance_types' => ResistanceTypeEnum::toSelectOptions(),
            'modifier_types' => ModifierTypeEnum::toSelectOptions(),
            'triggers' => Trigger::toSelectOptions('name', 'slug'),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function store(Request $request)
    {
        $action = $this->validateAndSave($request);

        return redirect()->route('admin.actions.index')->withMessage("{$action->name} created successfully.");
    }

    public function update(Request $request, Action $action)
    {
        $action = $this->validateAndSave($request, $action);

        return redirect()->route('admin.actions.index')->withMessage("{$action->name} has been updated.");
    }

    public function delete(Request $request, Action $action)
    {
        $name = $action->name;
        $action->delete();

        return redirect()->route('admin.actions.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Action $action = null): Action
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(ActionTypeEnum::class)],
            'is_signature' => ['required', 'boolean'],
            'costs_stone' => ['required', 'boolean'],
            'range' => ['nullable', 'integer'],
            'range_type' => ['nullable', 'string', Rule::enum(ActionRangeTypeEnum::class)],
            'stat' => ['nullable', 'integer'],
            'stat_suits' => ['nullable', 'string'],
            'stat_modifier' => ['nullable', 'string', Rule::enum(ModifierTypeEnum::class)],
            'resisted_by' => ['nullable', 'string', Rule::enum(ResistanceTypeEnum::class)],
            'target_number' => ['nullable', 'integer'],
            'target_suits' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'triggers' => ['nullable', 'array'],
            'characters' => ['nullable', 'array'],
        ]);

        $triggers = Trigger::whereIn('name', $validated['triggers'])->get();
        unset($validated['triggers']);

        $characters = Character::whereIn('display_name', $validated['characters'])->get();
        unset($validated['characters']);

        if (! $action) {
            $action = Action::create($validated);
            $action->update([
                'slug' => $action->id.'-'.$action->slug,
            ]);
        } else {
            $action->update($validated);
        }

        $action->triggers()->sync($triggers->pluck('id'));
        $action->characters()->sync($characters->pluck('id'));

        return $action;
    }
}
