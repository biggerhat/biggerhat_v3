<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Crew;
use App\Models\TOS\CrewUnit;
use App\Models\TOS\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MVP TOS Crew Builder. One crew per click — pick an Allegiance, add Units
 * (Scrip-budgeted by the Commander), attach Assets per Unit. Rule enforcement
 * leans on existing model helpers: `Allegiance->units()` (direct hires),
 * `Unit::hireableInto($alle)` (direct + Neutral pool), and
 * `Asset::canAttachTo($unit)` for limit checks.
 *
 * Save/load is per authenticated user. Public sharing is out-of-scope for
 * the MVP.
 */
class CrewController extends Controller
{
    public function index()
    {
        $crews = Crew::query()
            ->where('user_id', Auth::id())
            ->with(['allegiance:id,slug,name,color_slug', 'crewUnits:id,crew_id,unit_id,is_commander'])
            ->orderByDesc('updated_at')
            ->get();

        return inertia('TOS/Crews/Index', [
            'crews' => $crews,
        ]);
    }

    public function create()
    {
        return inertia('TOS/Crews/Create', [
            'allegiances' => fn () => Allegiance::query()
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'type', 'is_syndicate', 'color_slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $crew = Crew::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return redirect()->route('tos.crews.view', $crew->slug);
    }

    public function view(Crew $crew)
    {
        $this->authorizeCrew($crew);

        $crew->load([
            'allegiance:id,slug,name,type,color_slug',
            'crewUnits.unit:id,slug,name,title,scrip',
            'crewUnits.unit.specialUnitRules:id,slug,name',
            // Sculpts only need id+slug for the linkable card art; full
            // image columns and box references are loaded on the unit-view
            // page, not here.
            'crewUnits.unit.sculpts:id,unit_id,slug',
            'crewUnits.assets:id,slug,name,scrip_cost',
        ]);

        // Hireable pool for the picker — direct allegiance attachments PLUS
        // Neutral (matching-type) units, courtesy of Unit::hireableInto.
        $hireable = Unit::hireableInto($crew->allegiance)
            ->notCombinedArmsChild()
            ->with(['specialUnitRules:id,slug,name', 'sculpts:id,unit_id,slug'])
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'title', 'scrip']);

        // Hireable assets — those flagged for this Allegiance OR with no
        // allegiance restriction. Limit-by-Unit-name etc. is enforced when
        // *attaching*, not when listing.
        $assets = Asset::query()
            ->where(function ($q) use ($crew) {
                $q->whereDoesntHave('allegiances')
                    ->orWhereHas('allegiances', fn ($inner) => $inner->where('tos_allegiances.id', $crew->allegiance_id));
            })
            ->with(['limits', 'allegiances:id,slug,name'])
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'scrip_cost']);

        return inertia('TOS/Crews/View', [
            'crew' => $crew,
            'scrip_spent' => $crew->scripSpent(),
            'hireable_units' => $hireable,
            'available_assets' => $assets,
        ]);
    }

    public function update(Request $request, Crew $crew): RedirectResponse
    {
        $this->authorizeCrew($crew);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $crew->update($validated);

        return redirect()->route('tos.crews.view', $crew->slug);
    }

    public function delete(Crew $crew): RedirectResponse
    {
        $this->authorizeCrew($crew);

        $crew->delete();

        return redirect()->route('tos.crews.index')->withMessage('Crew deleted.');
    }

    public function addUnit(Request $request, Crew $crew): RedirectResponse
    {
        $this->authorizeCrew($crew);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:tos_units,id'],
            'is_commander' => ['nullable', 'boolean'],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);

        // Reject hires the rules wouldn't allow — keeps the saved crew valid.
        $isHireable = Unit::hireableInto($crew->allegiance)->where('tos_units.id', $unit->id)->exists();
        if (! $isHireable) {
            return back()->withErrors(['unit_id' => "{$unit->name} can't be hired into this Allegiance."]);
        }

        DB::transaction(function () use ($crew, $unit, $validated) {
            // Only one Commander per crew — flip the flag off everywhere else
            // first when promoting a new one.
            if (! empty($validated['is_commander'])) {
                $crew->crewUnits()->update(['is_commander' => false]);
            }

            $position = (int) ($crew->crewUnits()->max('position') ?? -1) + 1;

            CrewUnit::create([
                'crew_id' => $crew->id,
                'unit_id' => $unit->id,
                'is_commander' => (bool) ($validated['is_commander'] ?? false),
                'position' => $position,
            ]);
        });

        return back();
    }

    public function removeUnit(Crew $crew, CrewUnit $crewUnit): RedirectResponse
    {
        $this->authorizeCrew($crew);
        abort_unless($crewUnit->crew_id === $crew->id, 404);

        $crewUnit->delete();

        return back();
    }

    public function attachAsset(Request $request, Crew $crew, CrewUnit $crewUnit): RedirectResponse
    {
        $this->authorizeCrew($crew);
        abort_unless($crewUnit->crew_id === $crew->id, 404);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:tos_assets,id'],
        ]);

        $asset = Asset::findOrFail($validated['asset_id']);
        $crewUnit->load('unit');

        if (! $asset->canAttachTo($crewUnit->unit)) {
            return back()->withErrors(['asset_id' => "{$asset->name} can't be attached to {$crewUnit->unit->name}."]);
        }

        $crewUnit->assets()->syncWithoutDetaching([$asset->id]);

        return back();
    }

    public function detachAsset(Crew $crew, CrewUnit $crewUnit, Asset $asset): RedirectResponse
    {
        $this->authorizeCrew($crew);
        abort_unless($crewUnit->crew_id === $crew->id, 404);

        $crewUnit->assets()->detach($asset->id);

        return back();
    }

    private function authorizeCrew(Crew $crew): void
    {
        abort_unless($crew->user_id === Auth::id(), 403);
    }
}
