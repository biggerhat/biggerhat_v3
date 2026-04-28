<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TOS Company Builder. One Company per click — pick an Allegiance, add Units
 * (Scrip-budgeted by the Commander), attach Assets per Unit. Rule enforcement
 * leans on existing model helpers: `Allegiance->units()` (direct hires),
 * `Unit::hireableInto($alle)` (direct + Neutral pool), and
 * `Asset::canAttachTo($unit)` for limit checks.
 *
 * Save/load is per authenticated user. Public sharing is out-of-scope for
 * the MVP.
 */
class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::query()
            ->where('user_id', Auth::id())
            ->with(['allegiance:id,slug,name,type,secondary_type,color_slug', 'companyUnits:id,company_id,unit_id,is_commander'])
            ->orderByDesc('updated_at')
            ->get();

        return inertia('TOS/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function create()
    {
        return inertia('TOS/Companies/Create', [
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

        $company = Company::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return redirect()->route('tos.companies.view', $company->slug);
    }

    public function view(Company $company)
    {
        $this->authorizeCompany($company);

        $company->load([
            // secondary_type is required because Unit::hireableInto reads
            // $allegiance->typeValues() which dereferences both type columns.
            'allegiance:id,slug,name,type,secondary_type,color_slug',
            'companyUnits.unit:id,slug,name,title,scrip,combined_arms_child_id,restriction',
            'companyUnits.unit.specialUnitRules:id,slug,name',
            // Sculpts only need id+slug for the linkable card art; full
            // image columns and box references are loaded on the unit-view
            // page, not here.
            'companyUnits.unit.sculpts:id,unit_id,slug',
            'companyUnits.assets:id,slug,name,scrip_cost',
            'companyUnits.assets.limits',
        ]);

        // Hireable pool for the picker — direct allegiance attachments PLUS
        // Neutral (matching-type) units, courtesy of Unit::hireableInto.
        // Each unit is annotated with a `hire_category` so the UI can chip
        // it (Direct/Neutral) the way Malifaux's Crew Builder shows in-keyword
        // vs versatile/ook badges.
        $hireable = Unit::hireableInto($company->allegiance)
            ->notCombinedArmsChild()
            ->with(['specialUnitRules:id,slug,name', 'sculpts:id,unit_id,slug', 'allegiances:id'])
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'title', 'scrip', 'restriction', 'combined_arms_child_id'])
            ->map(function (Unit $u) use ($company) {
                $direct = $u->allegiances->contains('id', $company->allegiance_id);
                $u->setAttribute('hire_category', $direct ? 'direct' : 'neutral');

                return $u;
            });

        // Hireable assets — those flagged for this Allegiance OR with no
        // allegiance restriction.
        $assets = Asset::query()
            ->where(function ($q) use ($company) {
                $q->whereDoesntHave('allegiances')
                    ->orWhereHas('allegiances', fn ($inner) => $inner->where('tos_allegiances.id', $company->allegiance_id));
            })
            ->with(['limits', 'allegiances:id,slug,name'])
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'scrip_cost'])
            ->map(function (Asset $a) use ($company) {
                // Annotate with already-attached state so the picker UI can
                // gray out Unique Assets that are already on the Company
                // without a round trip.
                $a->setAttribute('already_attached', $company->hasAssetAttached($a));

                return $a;
            });

        return inertia('TOS/Companies/View', [
            'company' => $company,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
            'has_commander' => $company->hasCommander(),
            'hireable_units' => $hireable,
            'available_assets' => $assets,
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $company->update($validated);

        return redirect()->route('tos.companies.view', $company->slug);
    }

    public function delete(Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $company->delete();

        return redirect()->route('tos.companies.index')->withMessage('Company deleted.');
    }

    public function addUnit(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:tos_units,id'],
            'is_commander' => ['nullable', 'boolean'],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $promotingCommander = ! empty($validated['is_commander']);

        // Pre-load the relations both `scripBudget()` and `scripSpent()` walk
        // so subsequent helper calls don't fall through to `loadedCompanyUnits()`'s
        // implicit query — predictable cost regardless of which guard fires.
        $company->load(['companyUnits.unit:id,scrip', 'companyUnits.assets:id,scrip_cost']);

        // Reject hires the rules wouldn't allow — keeps the saved company
        // valid.
        $isHireable = Unit::hireableInto($company->allegiance)->where('tos_units.id', $unit->id)->exists();
        if (! $isHireable) {
            return back()->withErrors(['unit_id' => "{$unit->name} can't be hired into this Allegiance."]);
        }

        // Scrip budget — Commanders provide budget so they're never rejected;
        // every other hire has to fit under the remaining scrip.
        if (! $promotingCommander) {
            $cost = (int) ($unit->scrip ?? 0);
            if ($cost > $company->scripRemaining()) {
                return back()->withErrors([
                    'unit_id' => "Hiring {$unit->name} ({$cost} Scrip) would exceed the Commander's budget by ".($cost - $company->scripRemaining()).' Scrip.',
                ]);
            }
        }

        DB::transaction(function () use ($company, $unit, $promotingCommander) {
            // Only one Commander per Company — flip the flag off everywhere
            // else first when promoting a new one.
            if ($promotingCommander) {
                $company->companyUnits()->update(['is_commander' => false]);
            }

            $position = (int) ($company->companyUnits()->max('position') ?? -1) + 1;

            CompanyUnit::create([
                'company_id' => $company->id,
                'unit_id' => $unit->id,
                'is_commander' => $promotingCommander,
                'is_combined_arms_child' => false,
                'position' => $position,
            ]);

            // Combined Arms parent → child auto-attach (rulebook p. 11). The
            // child enters play with its parent automatically; we stamp it
            // `is_combined_arms_child=true` so the UI can render it nested
            // under the parent and `removeUnit` blocks standalone removal.
            // The child's Scrip is intentionally not budget-checked here —
            // by rulebook the child rides on the parent's Scrip.
            if ($unit->combined_arms_child_id) {
                CompanyUnit::create([
                    'company_id' => $company->id,
                    'unit_id' => $unit->combined_arms_child_id,
                    'is_commander' => false,
                    'is_combined_arms_child' => true,
                    'position' => $position + 1,
                ]);
            }
        });

        return back();
    }

    public function removeUnit(Company $company, CompanyUnit $companyUnit): RedirectResponse
    {
        $this->authorizeCompany($company);
        abort_unless($companyUnit->company_id === $company->id, 404);

        // Combined Arms children can't leave the Company on their own — they
        // ride with the parent (rulebook p. 11). Block direct removal so the
        // saved Company stays consistent.
        if ($companyUnit->is_combined_arms_child) {
            return back()->withErrors([
                'unit_id' => 'This unit was hired automatically by its Combined Arms parent and can only be removed by removing the parent.',
            ]);
        }

        DB::transaction(function () use ($company, $companyUnit) {
            // If the row being removed is itself a Combined Arms parent, drop
            // the child rows it pulled in too.
            $companyUnit->loadMissing('unit:id,combined_arms_child_id');
            $childUnitId = $companyUnit->unit->combined_arms_child_id ?? null;
            if ($childUnitId !== null) {
                $company->companyUnits()
                    ->where('unit_id', $childUnitId)
                    ->where('is_combined_arms_child', true)
                    ->delete();
            }

            $companyUnit->delete();
        });

        return back();
    }

    public function attachAsset(Request $request, Company $company, CompanyUnit $companyUnit): RedirectResponse
    {
        $this->authorizeCompany($company);
        abort_unless($companyUnit->company_id === $company->id, 404);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:tos_assets,id'],
        ]);

        $asset = Asset::with('limits')->findOrFail($validated['asset_id']);
        $companyUnit->load(['unit', 'assets.limits']);
        $company->load(['companyUnits.unit:id,scrip', 'companyUnits.assets.limits']);

        if (! $asset->canAttachTo($companyUnit->unit)) {
            return back()->withErrors(['asset_id' => "{$asset->name} can't be attached to {$companyUnit->unit->name}."]);
        }

        // Unique cap (rulebook p. 12) — same Asset can't appear twice
        // anywhere in the Company.
        if ($asset->isUnique() && $company->hasAssetAttached($asset)) {
            return back()->withErrors(['asset_id' => "{$asset->name} is Unique — it's already attached elsewhere in this Company."]);
        }

        // Slot collision (per-unit) — two Assets can't occupy the same Slot
        // location on the same company_unit. Compares case-insensitively
        // since Locations are free-form strings.
        $assetSlots = $asset->slotLocations();
        if (! empty($assetSlots)) {
            $occupied = [];
            foreach ($companyUnit->assets as $existing) {
                foreach ($existing->slotLocations() as $loc) {
                    $occupied[] = $loc;
                }
            }
            $conflict = array_values(array_intersect($assetSlots, $occupied));
            if (! empty($conflict)) {
                return back()->withErrors([
                    'asset_id' => "{$companyUnit->unit->name}'s ".$conflict[0].' Slot is already occupied.',
                ]);
            }
        }

        // Scrip cap — adding this Asset can't push the Company over the
        // Commander's budget.
        $cost = (int) ($asset->scrip_cost ?? 0);
        if ($cost > $company->scripRemaining()) {
            return back()->withErrors([
                'asset_id' => "Attaching {$asset->name} ({$cost} Scrip) would exceed the Commander's budget by ".($cost - $company->scripRemaining()).' Scrip.',
            ]);
        }

        $companyUnit->assets()->syncWithoutDetaching([$asset->id]);

        return back();
    }

    public function detachAsset(Company $company, CompanyUnit $companyUnit, Asset $asset): RedirectResponse
    {
        $this->authorizeCompany($company);
        abort_unless($companyUnit->company_id === $company->id, 404);

        $companyUnit->assets()->detach($asset->id);

        return back();
    }

    private function authorizeCompany(Company $company): void
    {
        abort_unless($company->user_id === Auth::id(), 403);
    }
}
