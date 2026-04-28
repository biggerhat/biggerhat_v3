<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\UsageLimitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreActionRequest;
use App\Http\Requests\TOS\Admin\UpdateActionRequest;
use App\Models\TOS\Action;
use Illuminate\Http\Request;

class ActionAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/TOS/Actions/Index', [
            'actions' => Action::with('typeLinks:id,action_id,type')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'av', 'av_target', 'av_suits', 'tn', 'range', 'usage_limit']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Actions/ActionForm', [
            'action_types' => fn () => ActionTypeEnum::toSelectOptions(),
            'usage_limits' => fn () => UsageLimitEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Action $action)
    {
        $action->load('typeLinks');

        return inertia('Admin/TOS/Actions/ActionForm', [
            'action' => $action,
            'action_types' => fn () => ActionTypeEnum::toSelectOptions(),
            'usage_limits' => fn () => UsageLimitEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreActionRequest $request)
    {
        $data = $request->validated();
        $types = $data['types'] ?? [];
        unset($data['types']);

        $action = Action::create($data);
        $action->syncTypes(array_map(fn ($t) => ActionTypeEnum::from($t), $types));

        return redirect()->route('admin.tos.actions.index')->withMessage("{$action->name} created.");
    }

    public function update(UpdateActionRequest $request, Action $action)
    {
        $data = $request->validated();
        $types = $data['types'] ?? [];
        unset($data['types']);

        $action->update($data);
        $action->syncTypes(array_map(fn ($t) => ActionTypeEnum::from($t), $types));

        return redirect()->route('admin.tos.actions.index')->withMessage("{$action->name} updated.");
    }

    public function delete(Request $request, Action $action)
    {
        $name = $action->name;
        $action->delete();

        return redirect()->route('admin.tos.actions.index')->withMessage("{$name} deleted.");
    }
}
