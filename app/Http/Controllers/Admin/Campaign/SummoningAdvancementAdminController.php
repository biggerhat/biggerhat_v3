<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreSummoningAdvancementRequest;
use App\Http\Requests\Admin\Campaign\UpdateSummoningAdvancementRequest;
use App\Models\Campaign\SummoningAdvancement;
use Illuminate\Http\Request;

class SummoningAdvancementAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/SummoningAdvancement/Index', [
            'items' => SummoningAdvancement::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/SummoningAdvancement/Form');
    }

    public function edit(Request $request, SummoningAdvancement $summoningAdvancement)
    {
        return inertia('Admin/Campaign/SummoningAdvancement/Form', ['item' => $summoningAdvancement]);
    }

    public function store(StoreSummoningAdvancementRequest $request)
    {
        $row = SummoningAdvancement::create($request->validated());

        return redirect()->route('admin.campaign.summoning-advancements.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateSummoningAdvancementRequest $request, SummoningAdvancement $summoningAdvancement)
    {
        $summoningAdvancement->update($request->validated());

        return redirect()->route('admin.campaign.summoning-advancements.index')->withMessage("{$summoningAdvancement->name} updated.");
    }

    public function delete(Request $request, SummoningAdvancement $summoningAdvancement)
    {
        $name = $summoningAdvancement->name;
        $summoningAdvancement->delete();

        return redirect()->route('admin.campaign.summoning-advancements.index')->withMessage("{$name} deleted.");
    }
}
