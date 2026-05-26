<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreInjuryRequest;
use App\Http\Requests\Admin\Campaign\UpdateInjuryRequest;
use App\Models\Campaign\Injury;
use Illuminate\Http\Request;

class InjuryAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/Injury/Index', [
            'items' => Injury::orderByRaw('suit_pool ASC, flip_value IS NULL, flip_value ASC')
                ->get(['id', 'name', 'flip_value', 'suit_pool', 'is_traitor', 'is_close_call', 'annihilates_model']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/Injury/Form');
    }

    public function edit(Request $request, Injury $injury)
    {
        return inertia('Admin/Campaign/Injury/Form', ['item' => $injury]);
    }

    public function store(StoreInjuryRequest $request)
    {
        $row = Injury::create($request->validated());

        return redirect()->route('admin.campaign.injuries.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateInjuryRequest $request, Injury $injury)
    {
        $injury->update($request->validated());

        return redirect()->route('admin.campaign.injuries.index')->withMessage("{$injury->name} updated.");
    }

    public function delete(Request $request, Injury $injury)
    {
        $name = $injury->name;
        $injury->delete();

        return redirect()->route('admin.campaign.injuries.index')->withMessage("{$name} deleted.");
    }
}
