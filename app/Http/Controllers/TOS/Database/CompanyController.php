<?php

namespace App\Http\Controllers\TOS\Database;

use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Garrison;
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

    public function create(Request $request)
    {
        $preselectGarrisonId = $request->filled('garrison_id') ? (int) $request->get('garrison_id') : null;

        return inertia('TOS/Companies/Create', [
            'allegiances' => fn () => Allegiance::query()
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'type', 'is_syndicate', 'color_slug']),
            // The user's Garrisons are surfaced as an optional "Build from
            // Garrison" picker. Selecting one locks the Allegiance to match
            // and tells the Builder to filter the hireable pool down to
            // what's actually in the Garrison.
            'garrisons' => fn () => Garrison::query()
                ->where('user_id', Auth::id())
                ->with('allegiance:id,slug,name,color_slug')
                ->orderByDesc('updated_at')
                ->get(['id', 'slug', 'name', 'allegiance_id', 'format', 'updated_at']),
            'preselect_garrison_id' => $preselectGarrisonId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'garrison_id' => ['nullable', 'integer', 'exists:tos_garrisons,id'],
            'notes' => ['nullable', 'string'],
        ]);

        // If the user picked a Garrison, snap the allegiance to it (the
        // Builder UI already locks the picker, but server-side enforcement
        // means an out-of-band POST can't desync the two).
        if (! empty($validated['garrison_id'])) {
            $garrison = Garrison::where('id', $validated['garrison_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $validated['allegiance_id'] = $garrison->allegiance_id;
        }

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
            'garrison:id,slug,name,format,allegiance_id',
            'companyUnits.unit:id,slug,name,title,scrip,combined_arms_child_id,restriction',
            'companyUnits.unit.specialUnitRules:id,slug,name',
            // Sculpts include image columns now — the company-builder drawer
            // shows the FlipCard preview and lets the user switch sculpt
            // variants per company unit.
            'companyUnits.unit.sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
            'companyUnits.assets:id,slug,name,scrip_cost',
            'companyUnits.assets.limits',
        ]);

        // Pre-compute the Garrison's pool ids when this Company is tied to
        // one — used to intersect both pickers below. Distinct unit ids
        // because the Garrison can hold multiple rows of the same unit
        // (Same-Name cap), but for picker eligibility we only care that
        // the unit *appears at all* in the pool.
        $garrisonUnitIds = null;
        $garrisonAssetIds = null;
        if ($company->garrison_id) {
            // reorder() drops the relation's ORDER BY position — MySQL rejects
            // DISTINCT combined with an ORDER BY column that isn't selected.
            $garrisonUnitIds = $company->garrison->garrisonUnits()->reorder()->distinct()->pluck('unit_id');
            $garrisonAssetIds = $company->garrison->assets()->pluck('tos_assets.id');
        }

        // Picker pools are wrapped in lazy closures so partial reloads
        // (`router.post({ only: ['company', 'scrip_spent', ...] })`) skip
        // the heavy hireable / asset queries entirely — same pattern the
        // Crew Builder and Garrison Builder use. Inertia evaluates these
        // only on full visits or when the prop is explicitly requested.
        $hireableUnits = function () use ($company, $garrisonUnitIds) {
            $hireableQuery = Unit::hireableInto($company->allegiance)
                ->notCombinedArmsChild();
            if ($garrisonUnitIds !== null) {
                $hireableQuery->whereIn('tos_units.id', $garrisonUnitIds);
            }

            return $hireableQuery
                ->with([
                    'specialUnitRules:id,slug,name',
                    'sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
                    'allegiances:id',
                ])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'title', 'scrip', 'restriction', 'combined_arms_child_id'])
                ->map(function (Unit $u) use ($company) {
                    // hire_category drives the Direct/Neutral chip in the
                    // picker (mirrors Crew Builder's in-keyword vs OOK).
                    $direct = $u->allegiances->contains('id', $company->allegiance_id);
                    $u->setAttribute('hire_category', $direct ? 'direct' : 'neutral');

                    return $u;
                });
        };

        $availableAssets = function () use ($company, $garrisonAssetIds) {
            $assetsQuery = Asset::query()
                ->where(function ($q) use ($company) {
                    $q->whereDoesntHave('allegiances')
                        ->orWhereHas('allegiances', fn ($inner) => $inner->where('tos_allegiances.id', $company->allegiance_id));
                });
            if ($garrisonAssetIds !== null) {
                $assetsQuery->whereIn('tos_assets.id', $garrisonAssetIds);
            }

            return $assetsQuery
                ->with(['limits', 'allegiances:id,slug,name'])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'scrip_cost'])
                ->map(function (Asset $a) use ($company) {
                    // already_attached lets the picker grey out Unique
                    // Assets that are already in play without a round trip.
                    $a->setAttribute('already_attached', $company->hasAssetAttached($a));

                    return $a;
                });
        };

        return inertia('TOS/Companies/View', [
            'company' => $company,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
            'has_commander' => $company->hasCommander(),
            'hireable_units' => $hireableUnits,
            'available_assets' => $availableAssets,
            // Garrisons the user owns that can host this Company (same
            // Allegiance) — drives the "Build from Garrison" picker.
            // Lazy so partial reloads of pool actions skip it.
            'available_garrisons' => fn () => Garrison::query()
                ->where('user_id', Auth::id())
                ->where('allegiance_id', $company->allegiance_id)
                ->orderByDesc('updated_at')
                ->get(['id', 'slug', 'name', 'format', 'allegiance_id', 'updated_at']),
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

    /**
     * Tie or untie a Company to/from a Garrison from the View page. Setting
     * a Garrison snaps the allegiance to match (defensive — the picker
     * already locks it). Setting NULL drops the restriction; the existing
     * roster stays put even if items now fall outside what the prior
     * Garrison would have allowed (the Builder simply unrestricts going
     * forward — same call as a casual Company).
     */
    public function setGarrison(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'garrison_id' => ['nullable', 'integer', 'exists:tos_garrisons,id'],
        ]);

        if (! empty($validated['garrison_id'])) {
            $garrison = Garrison::where('id', $validated['garrison_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $company->update([
                'garrison_id' => $garrison->id,
                'allegiance_id' => $garrison->allegiance_id,
            ]);
        } else {
            $company->update(['garrison_id' => null]);
        }

        return back();
    }

    public function delete(Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $company->delete();

        return redirect()->route('tos.companies.index')->withMessage('Company deleted.');
    }

    /**
     * PDF export of the roster — print-ready single page mirroring the
     * Malifaux Crew Builder PDF flow (DomPDF + Blade). Owner-only; the
     * shared route can wire its own copy of this if public-PDF becomes a
     * Phase-2 ask.
     */
    public function downloadPdf(Company $company): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeCompany($company);

        $company->load([
            'allegiance:id,slug,name,type',
            'companyUnits.unit:id,slug,name,title,scrip,combined_arms_child_id,restriction',
            'companyUnits.unit.specialUnitRules:id,slug,name',
            'companyUnits.assets:id,slug,name,scrip_cost',
        ]);

        $renderable = $company->companyUnits
            ->reject(fn ($cu) => $cu->is_combined_arms_child)
            ->sortByDesc('is_commander')
            ->values();

        $childByParent = collect();
        foreach ($company->companyUnits as $cu) {
            if (! $cu->is_combined_arms_child) {
                continue;
            }
            $parent = $company->companyUnits->first(
                fn ($p) => ! $p->is_combined_arms_child && $p->unit->combined_arms_child_id === $cu->unit->id
            );
            if ($parent) {
                $childByParent->put($parent->unit->id, $cu);
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('PDF.Company', [
            'company' => $company,
            'renderable_units' => $renderable,
            'children_by_parent' => $childByParent,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
        ]);

        return $pdf->stream(\Illuminate\Support\Str::slug($company->name).'.pdf');
    }

    /**
     * Flip a Company between owner-only and publicly-shareable. Mirrors
     * the Malifaux Crew Builder's `togglePublic` pattern. The share URL
     * (`/tos/c/{share_code}`) is stable regardless of the toggle state —
     * just inaccessible while `is_public` is false.
     */
    public function togglePublic(Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $company->update(['is_public' => ! $company->is_public]);

        return back();
    }

    /**
     * Public read-only Company view, bound by share_code. Returns 404 when
     * the row is private — same outcome as a missing share code, so a
     * scraper can't distinguish "doesn't exist" from "owner made it private".
     */
    public function shared(string $share_code): \Inertia\Response
    {
        $company = Company::query()
            ->where('share_code', $share_code)
            ->where('is_public', true)
            ->firstOrFail();

        $company->load([
            'allegiance:id,slug,name,type,secondary_type,color_slug',
            'user:id,name',
            'companyUnits.unit:id,slug,name,title,scrip,combined_arms_child_id,restriction',
            'companyUnits.unit.specialUnitRules:id,slug,name',
            'companyUnits.unit.sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
            'companyUnits.assets:id,slug,name,scrip_cost',
            'companyUnits.assets.limits',
        ]);

        return inertia('TOS/Companies/Shared', [
            'company' => $company,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
        ]);
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

        // When this Company is being built from a Garrison, the unit must
        // also appear in the Garrison's declared pool — same enforcement
        // the picker UI applies, re-checked server-side for out-of-band
        // POSTs.
        if ($company->garrison_id) {
            $inPool = \App\Models\TOS\GarrisonUnit::query()
                ->where('garrison_id', $company->garrison_id)
                ->where('unit_id', $unit->id)
                ->exists();
            if (! $inPool) {
                return back()->withErrors(['unit_id' => "{$unit->name} isn't in your declared Garrison."]);
            }
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

    /**
     * Persist which sculpt variant the user wants displayed for a hired unit.
     * Drawer-driven — the FlipCard preview swaps front/back images live as
     * the user picks a different sculpt; saving here keeps the choice across
     * page loads. Validates that the sculpt actually belongs to the unit so
     * users can't pin an unrelated sculpt onto a different unit.
     */
    public function updateSculpt(Request $request, Company $company, CompanyUnit $companyUnit): RedirectResponse
    {
        $this->authorizeCompany($company);
        abort_unless($companyUnit->company_id === $company->id, 404);

        $validated = $request->validate([
            'sculpt_id' => ['nullable', 'integer', 'exists:tos_unit_sculpts,id'],
        ]);

        if ($validated['sculpt_id'] !== null) {
            $sculptUnitId = \App\Models\TOS\UnitSculpt::whereKey($validated['sculpt_id'])->value('unit_id');
            abort_unless($sculptUnitId === $companyUnit->unit_id, 422, 'That sculpt does not belong to this unit.');
        }

        $companyUnit->update(['sculpt_id' => $validated['sculpt_id']]);

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

        // Garrison-restricted Companies pull only from the Garrison's
        // declared Asset pool — mirrors the Unit gate above. Use the
        // pivot relation rather than a raw DB query so the table name
        // stays in one place (Company belongsTo Garrison; Garrison's
        // assets() pivot is the single source of truth).
        if ($company->garrison_id) {
            $inPool = $company->garrison->assets()
                ->where('tos_assets.id', $asset->id)
                ->exists();
            if (! $inPool) {
                return back()->withErrors(['asset_id' => "{$asset->name} isn't in your declared Garrison's Asset pool."]);
            }
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
