<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreCrewCardRequest;
use App\Http\Requests\Admin\Campaign\UpdateCrewCardRequest;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignCrewCard;
use Illuminate\Http\Request;

class CrewCardAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/CrewCard/Index', [
            'items' => CampaignCrewCard::orderBy('name')
                ->get(['id', 'name', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice']),
        ]);
    }

    private function formProps(): array
    {
        return [
            'all_actions' => fn () => Action::orderBy('name')->get(['id', 'name']),
            'all_abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/CrewCard/Form', $this->formProps());
    }

    public function edit(Request $request, CampaignCrewCard $crewCard)
    {
        $crewCard->load(['actions:id,name', 'abilities:id,name']);

        return inertia('Admin/Campaign/CrewCard/Form', array_merge(
            ['item' => $crewCard],
            $this->formProps(),
        ));
    }

    public function store(StoreCrewCardRequest $request)
    {
        $validated = $request->validated();
        $actionIds = $validated['action_ids'] ?? [];
        $abilityIds = $validated['ability_ids'] ?? [];
        unset($validated['action_ids'], $validated['ability_ids']);

        $row = CampaignCrewCard::create($validated);
        $row->actions()->sync($actionIds);
        $row->abilities()->sync($abilityIds);

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateCrewCardRequest $request, CampaignCrewCard $crewCard)
    {
        $validated = $request->validated();
        $actionIds = $validated['action_ids'] ?? [];
        $abilityIds = $validated['ability_ids'] ?? [];
        unset($validated['action_ids'], $validated['ability_ids']);

        $crewCard->update($validated);
        $crewCard->actions()->sync($actionIds);
        $crewCard->abilities()->sync($abilityIds);

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$crewCard->name} updated.");
    }

    public function delete(Request $request, CampaignCrewCard $crewCard)
    {
        $name = $crewCard->name;
        $crewCard->delete();

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$name} deleted.");
    }
}
