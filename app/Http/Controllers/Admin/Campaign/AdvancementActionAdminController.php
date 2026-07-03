<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreAdvancementActionRequest;
use App\Http\Requests\Admin\Campaign\UpdateAdvancementActionRequest;
use App\Models\Action;
use App\Models\Campaign\AdvancementAction;
use Illuminate\Http\Request;

class AdvancementActionAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/Advancement/ActionIndex', [
            'items' => AdvancementAction::query()
                ->with('action:id,name')
                ->orderByRaw('flip_value IS NULL, flip_value ASC')
                ->orderBy('talent_name')
                ->get(['id', 'flip_value', 'is_joker', 'is_always_available', 'talent_name', 'action_id']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/Advancement/ActionForm', $this->formData());
    }

    public function edit(Request $request, AdvancementAction $advancementAction)
    {
        return inertia('Admin/Campaign/Advancement/ActionForm', [
            'item' => $advancementAction->loadMissing('action:id,name'),
            ...$this->formData(),
        ]);
    }

    public function store(StoreAdvancementActionRequest $request)
    {
        $row = AdvancementAction::create($request->validated());

        return redirect()->route('admin.campaign.advancement-action.index')->withMessage("{$row->talent_name} created.");
    }

    public function update(UpdateAdvancementActionRequest $request, AdvancementAction $advancementAction)
    {
        $advancementAction->update($request->validated());

        return redirect()->route('admin.campaign.advancement-action.index')->withMessage("{$advancementAction->talent_name} updated.");
    }

    public function delete(Request $request, AdvancementAction $advancementAction)
    {
        $name = $advancementAction->talent_name;
        $advancementAction->delete();

        return redirect()->route('admin.campaign.advancement-action.index')->withMessage("{$name} deleted.");
    }

    private function formData(): array
    {
        return [
            'actions' => fn () => Action::toSelectOptions('name'),
        ];
    }
}
