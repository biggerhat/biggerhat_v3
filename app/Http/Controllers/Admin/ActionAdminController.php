<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\ModifierTypeEnum;
use App\Enums\ResistanceTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActionRequest;
use App\Models\Action;
use App\Models\Character;
use App\Models\Trigger;
use Illuminate\Http\Request;

class ActionAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Actions/Index', [
            'actions' => Action::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Actions/ActionForm', $this->getFormData());
    }

    public function edit(Request $request, Action $action)
    {
        return inertia('Admin/Actions/ActionForm', array_merge(
            ['action' => $action->loadMissing(['triggers', 'characters'])],
            $this->getFormData(),
        ));
    }

    public function store(ActionRequest $request)
    {
        $action = $this->validateAndSave($request);

        return redirect()->route('admin.actions.index')->withMessage("{$action->name} created successfully.");
    }

    public function update(ActionRequest $request, Action $action)
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

    private function getFormData(): array
    {
        return [
            'action_types' => fn () => ActionTypeEnum::toSelectOptions(),
            'range_types' => fn () => ActionRangeTypeEnum::toSelectOptions(),
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'resistance_types' => fn () => ResistanceTypeEnum::toSelectOptions(),
            'modifier_types' => fn () => ModifierTypeEnum::toSelectOptions(),
            'triggers' => fn () => Trigger::toSelectOptions('name', 'slug'),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ];
    }

    private function validateAndSave(ActionRequest $request, ?Action $action = null): Action
    {
        $validated = $request->validated();

        if ($validated['game_mode_type'] !== GameModeTypeEnum::Campaign->value) {
            $validated['campaign_advancement_kind'] = null;
        }

        $triggers = Trigger::whereIn('name', $validated['triggers'] ?? [])->get();
        unset($validated['triggers']);

        $characters = Character::whereIn('display_name', $validated['characters'] ?? [])->get();
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
