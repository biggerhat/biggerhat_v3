<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreTotemRequest;
use App\Http\Requests\Admin\Campaign\UpdateTotemRequest;
use App\Models\Campaign\Totem;
use Illuminate\Http\Request;

class TotemAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/Totem/Index', [
            'items' => Totem::orderByRaw('is_black_joker DESC, is_red_joker DESC, flip_value ASC')
                ->get(['id', 'name', 'flip_value', 'is_black_joker', 'is_red_joker', 'df', 'wp', 'sp', 'health', 'is_mini_master']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/Totem/Form');
    }

    public function edit(Request $request, Totem $totem)
    {
        return inertia('Admin/Campaign/Totem/Form', ['item' => $totem]);
    }

    public function store(StoreTotemRequest $request)
    {
        $row = Totem::create($request->validated());

        return redirect()->route('admin.campaign.totems.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateTotemRequest $request, Totem $totem)
    {
        $totem->update($request->validated());

        return redirect()->route('admin.campaign.totems.index')->withMessage("{$totem->name} updated.");
    }

    public function delete(Request $request, Totem $totem)
    {
        $name = $totem->name;
        $totem->delete();

        return redirect()->route('admin.campaign.totems.index')->withMessage("{$name} deleted.");
    }
}
