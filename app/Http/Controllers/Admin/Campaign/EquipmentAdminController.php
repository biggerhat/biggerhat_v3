<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreEquipmentRequest;
use App\Http\Requests\Admin\Campaign\UpdateEquipmentRequest;
use App\Models\Campaign\Equipment;
use Illuminate\Http\Request;

class EquipmentAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/Equipment/Index', [
            'items' => Equipment::orderByRaw('is_always_available DESC, br IS NULL, br ASC, name ASC')
                ->get(['id', 'name', 'br', 'cc', 'is_always_available', 'ttw_only', 'is_omens_mark', 'pool_suit_a', 'pool_suit_b', 'is_unique', 'leader_only']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/Equipment/Form');
    }

    public function edit(Request $request, Equipment $equipment)
    {
        return inertia('Admin/Campaign/Equipment/Form', ['item' => $equipment]);
    }

    public function store(StoreEquipmentRequest $request)
    {
        $row = Equipment::create($request->validated());

        return redirect()->route('admin.campaign.equipment.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment)
    {
        $equipment->update($request->validated());

        return redirect()->route('admin.campaign.equipment.index')->withMessage("{$equipment->name} updated.");
    }

    public function delete(Request $request, Equipment $equipment)
    {
        $name = $equipment->name;
        $equipment->delete();

        return redirect()->route('admin.campaign.equipment.index')->withMessage("{$name} deleted.");
    }
}
