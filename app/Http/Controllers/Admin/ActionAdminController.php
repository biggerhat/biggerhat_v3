<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\GameModeTypeEnum;
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

    private function validateAndSave(Request $request, ?Action $action = null): Action
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'game_mode_type' => ['required', 'string', Rule::enum(GameModeTypeEnum::class)],
            'type' => ['required', 'string', Rule::enum(ActionTypeEnum::class)],
            'is_signature' => ['required', 'boolean'],
            'stone_cost' => ['required', 'integer', 'min:0', 'max:10'],
            'range' => ['nullable', 'string'],
            'range_type' => ['nullable', 'string', Rule::enum(ActionRangeTypeEnum::class)],
            'stat' => ['nullable', 'string'],
            'stat_suits' => ['nullable', 'string'],
            'stat_modifier' => ['nullable', 'string', Rule::enum(ModifierTypeEnum::class)],
            'resisted_by' => ['nullable', 'string', Rule::enum(ResistanceTypeEnum::class)],
            'target_number' => ['nullable', 'string'],
            'target_suits' => ['nullable', 'string'],
            'damage' => ['nullable', 'string'],
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
