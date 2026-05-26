<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\LeaderArchetypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreLeaderArchetypeRequest;
use App\Http\Requests\Admin\Campaign\UpdateLeaderArchetypeRequest;
use App\Models\Campaign\LeaderArchetype;
use Illuminate\Http\Request;

class LeaderArchetypeAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/LeaderArchetype/Index', [
            'archetypes' => LeaderArchetype::orderBy('name')
                ->get(['id', 'slug', 'name', 'df', 'wp', 'sp', 'health', 'attack_gets_trigger']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/LeaderArchetype/Form', [
            'slug_options' => fn () => LeaderArchetypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, LeaderArchetype $archetype)
    {
        return inertia('Admin/Campaign/LeaderArchetype/Form', [
            'archetype' => $archetype,
            'slug_options' => fn () => LeaderArchetypeEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreLeaderArchetypeRequest $request)
    {
        $archetype = LeaderArchetype::create($request->validated());

        return redirect()->route('admin.campaign.leader-archetypes.index')
            ->withMessage("{$archetype->name} created.");
    }

    public function update(UpdateLeaderArchetypeRequest $request, LeaderArchetype $archetype)
    {
        $archetype->update($request->validated());

        return redirect()->route('admin.campaign.leader-archetypes.index')
            ->withMessage("{$archetype->name} updated.");
    }

    public function delete(Request $request, LeaderArchetype $archetype)
    {
        $name = $archetype->name;
        $archetype->delete();

        return redirect()->route('admin.campaign.leader-archetypes.index')
            ->withMessage("{$name} deleted.");
    }
}
