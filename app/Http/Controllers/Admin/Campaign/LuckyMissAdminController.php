<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreLuckyMissRequest;
use App\Http\Requests\Admin\Campaign\UpdateLuckyMissRequest;
use App\Models\Ability;
use App\Models\Campaign\LuckyMiss;
use Illuminate\Http\Request;

class LuckyMissAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/LuckyMiss/Index', [
            'items' => LuckyMiss::query()
                ->with('ability:id,name')
                ->orderByRaw('flip_value IS NULL, flip_value ASC')
                ->get(['id', 'name', 'flip_value', 'is_doppelganger', 'ability_id']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/LuckyMiss/Form', $this->formData());
    }

    public function edit(Request $request, LuckyMiss $luckyMiss)
    {
        return inertia('Admin/Campaign/LuckyMiss/Form', [
            'item' => $luckyMiss->loadMissing('ability:id,name'),
            ...$this->formData(),
        ]);
    }

    public function store(StoreLuckyMissRequest $request)
    {
        $row = LuckyMiss::create($request->validated());

        return redirect()->route('admin.campaign.lucky-miss.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateLuckyMissRequest $request, LuckyMiss $luckyMiss)
    {
        $luckyMiss->update($request->validated());

        return redirect()->route('admin.campaign.lucky-miss.index')->withMessage("{$luckyMiss->name} updated.");
    }

    public function delete(Request $request, LuckyMiss $luckyMiss)
    {
        $name = $luckyMiss->name;
        $luckyMiss->delete();

        return redirect()->route('admin.campaign.lucky-miss.index')->withMessage("{$name} deleted.");
    }

    private function formData(): array
    {
        return [
            'abilities' => fn () => Ability::toSelectOptions('name'),
        ];
    }
}
