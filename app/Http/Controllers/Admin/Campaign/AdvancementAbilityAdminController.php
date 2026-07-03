<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreAdvancementAbilityRequest;
use App\Http\Requests\Admin\Campaign\UpdateAdvancementAbilityRequest;
use App\Models\Ability;
use App\Models\Campaign\AdvancementAbility;
use Illuminate\Http\Request;

class AdvancementAbilityAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/Advancement/AbilityIndex', [
            'items' => AdvancementAbility::query()
                ->with('ability:id,name')
                ->orderByRaw('flip_value IS NULL, flip_value ASC')
                ->orderBy('talent_name')
                ->get(['id', 'flip_value', 'is_joker', 'is_always_available', 'talent_name', 'ability_id']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/Advancement/AbilityForm', $this->formData());
    }

    public function edit(Request $request, AdvancementAbility $advancementAbility)
    {
        return inertia('Admin/Campaign/Advancement/AbilityForm', [
            'item' => $advancementAbility->loadMissing('ability:id,name'),
            ...$this->formData(),
        ]);
    }

    public function store(StoreAdvancementAbilityRequest $request)
    {
        $row = AdvancementAbility::create($request->validated());

        return redirect()->route('admin.campaign.advancement-ability.index')->withMessage("{$row->talent_name} created.");
    }

    public function update(UpdateAdvancementAbilityRequest $request, AdvancementAbility $advancementAbility)
    {
        $advancementAbility->update($request->validated());

        return redirect()->route('admin.campaign.advancement-ability.index')->withMessage("{$advancementAbility->talent_name} updated.");
    }

    public function delete(Request $request, AdvancementAbility $advancementAbility)
    {
        $name = $advancementAbility->talent_name;
        $advancementAbility->delete();

        return redirect()->route('admin.campaign.advancement-ability.index')->withMessage("{$name} deleted.");
    }

    private function formData(): array
    {
        return [
            'abilities' => fn () => Ability::toSelectOptions('name'),
            'defensive_ability_types' => fn () => DefensiveAbilityTypeEnum::toSelectOptions(),
        ];
    }
}
