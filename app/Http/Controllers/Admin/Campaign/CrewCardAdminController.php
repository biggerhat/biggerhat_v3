<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreCrewCardRequest;
use App\Http\Requests\Admin\Campaign\UpdateCrewCardRequest;
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

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/CrewCard/Form');
    }

    public function edit(Request $request, CampaignCrewCard $crewCard)
    {
        return inertia('Admin/Campaign/CrewCard/Form', ['item' => $crewCard]);
    }

    public function store(StoreCrewCardRequest $request)
    {
        $row = CampaignCrewCard::create($request->validated());

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateCrewCardRequest $request, CampaignCrewCard $crewCard)
    {
        $crewCard->update($request->validated());

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$crewCard->name} updated.");
    }

    public function delete(Request $request, CampaignCrewCard $crewCard)
    {
        $name = $crewCard->name;
        $crewCard->delete();

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$name} deleted.");
    }
}
