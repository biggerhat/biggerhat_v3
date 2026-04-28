<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreUnitRequest;
use App\Http\Requests\TOS\Admin\UpdateUnitRequest;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/TOS/Units/Index', [
            'units' => Unit::with(['allegiances:id,name', 'specialUnitRules:id,slug,name', 'sides:id,unit_id,side'])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'title', 'scrip', 'tactics', 'restriction', 'sort_order']),
            'special_rules' => SpecialUnitRule::orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Units/UnitForm', $this->formPayload(null));
    }

    public function edit(Request $request, Unit $unit)
    {
        $unit->load(['allegiances:id', 'specialUnitRules', 'sides.abilities:id', 'sides.actions:id']);

        return inertia('Admin/TOS/Units/UnitForm', ['unit' => $unit] + $this->formPayload($unit));
    }

    /**
     * Shared admin form payload — every dropdown is wrapped in a closure for
     * Inertia partial-reload friendliness. The Combined Arms picker excludes
     * the unit being edited so a Unit can't designate itself as its own
     * Combined Arms child.
     *
     * @return array<string, callable>
     */
    private function formPayload(?Unit $exclude): array
    {
        return [
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'is_syndicate']),
            'special_rules' => fn () => SpecialUnitRule::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
            'actions' => fn () => Action::with('typeLinks:id,action_id,type')->orderBy('name')->get(['id', 'name']),
            'units' => fn () => Unit::query()
                ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude->id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'restrictions' => fn () => AllegianceTypeEnum::toSelectOptions(),
        ];
    }

    public function store(StoreUnitRequest $request)
    {
        $data = $request->validated();

        $unit = DB::transaction(function () use ($data) {
            $unit = Unit::create([
                'name' => $data['name'],
                'title' => $data['title'] ?? null,
                'scrip' => $data['scrip'],
                'tactics' => $data['tactics'] ?? null,
                'glory_tactics' => $data['glory_tactics'] ?? null,
                'description' => $data['description'] ?? null,
                'lore_text' => $data['lore_text'] ?? null,
                'restriction' => $data['restriction'] ?? null,
                'combined_arms_child_id' => $data['combined_arms_child_id'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncSides($unit, $data['sides']);
            $unit->allegiances()->sync($data['allegiance_ids'] ?? []);
            $this->syncSpecialRules($unit, $data['special_rules'] ?? []);

            return $unit;
        });

        return redirect()->route('admin.tos.units.index')->withMessage("{$unit->name} created successfully.");
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $data = $request->validated();

        DB::transaction(function () use ($unit, $data) {
            $unit->update([
                'name' => $data['name'],
                'title' => $data['title'] ?? null,
                'scrip' => $data['scrip'],
                'tactics' => $data['tactics'] ?? null,
                'glory_tactics' => $data['glory_tactics'] ?? null,
                'description' => $data['description'] ?? null,
                'lore_text' => $data['lore_text'] ?? null,
                'restriction' => $data['restriction'] ?? null,
                'combined_arms_child_id' => $data['combined_arms_child_id'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncSides($unit, $data['sides']);
            $unit->allegiances()->sync($data['allegiance_ids'] ?? []);
            $this->syncSpecialRules($unit, $data['special_rules'] ?? []);
        });

        return redirect()->route('admin.tos.units.index')->withMessage("{$unit->name} updated.");
    }

    public function delete(Request $request, Unit $unit)
    {
        $name = $unit->name;
        $unit->delete();

        return redirect()->route('admin.tos.units.index')->withMessage("{$name} deleted.");
    }

    /**
     * @param  array<int, array{side: string, speed: int, defense: int, willpower: int, armor: int, ability_ids?: array<int, int>, action_ids?: array<int, int>}>  $sides
     */
    private function syncSides(Unit $unit, array $sides): void
    {
        foreach ($sides as $sideData) {
            $side = UnitSide::updateOrCreate(
                ['unit_id' => $unit->id, 'side' => $sideData['side']],
                [
                    'speed' => $sideData['speed'],
                    'defense' => $sideData['defense'],
                    'willpower' => $sideData['willpower'],
                    'armor' => $sideData['armor'],
                ],
            );

            $abilityIds = $sideData['ability_ids'] ?? [];
            $side->abilities()->sync($this->withSortOrder($abilityIds));

            $actionIds = $sideData['action_ids'] ?? [];
            $side->actions()->sync($this->withSortOrder($actionIds));
        }

        // Defensive: ensure no orphan sides remain (e.g. side enum was changed elsewhere).
        $unit->sides()->whereNotIn('side', array_column($sides, 'side'))->delete();
    }

    /**
     * @param  array<int, array{special_unit_rule_id: int, parameters?: array<string, mixed>|null}>  $rules
     */
    private function syncSpecialRules(Unit $unit, array $rules): void
    {
        $sync = [];
        foreach ($rules as $r) {
            $sync[$r['special_unit_rule_id']] = ['parameters' => $r['parameters'] ?? null];
        }
        $unit->specialUnitRules()->sync($sync);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{sort_order: int}>
     */
    private function withSortOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }
}
