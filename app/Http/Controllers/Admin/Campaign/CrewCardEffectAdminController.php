<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreCrewCardEffectRequest;
use App\Http\Requests\Admin\Campaign\UpdateCrewCardEffectRequest;
use App\Models\Campaign\CrewCardEffect;
use Illuminate\Http\Request;

class CrewCardEffectAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/CrewCardEffect/Index', [
            'items' => CrewCardEffect::orderBy('name')
                ->get(['id', 'name', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/CrewCardEffect/Form');
    }

    public function edit(Request $request, CrewCardEffect $effect)
    {
        return inertia('Admin/Campaign/CrewCardEffect/Form', ['item' => $effect]);
    }

    public function store(StoreCrewCardEffectRequest $request)
    {
        $row = CrewCardEffect::create($request->validated());

        return redirect()->route('admin.campaign.crew-card-effects.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateCrewCardEffectRequest $request, CrewCardEffect $effect)
    {
        $effect->update($request->validated());

        return redirect()->route('admin.campaign.crew-card-effects.index')->withMessage("{$effect->name} updated.");
    }

    public function delete(Request $request, CrewCardEffect $effect)
    {
        $name = $effect->name;
        $effect->delete();

        return redirect()->route('admin.campaign.crew-card-effects.index')->withMessage("{$name} deleted.");
    }
}
