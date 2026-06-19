<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\GarrisonFormatEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Garrison;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            // Play formats (game size) for the standalone picker. A Garrison-
            // linked Company inherits its Garrison's format instead.
            'formats' => fn () => collect(GarrisonFormatEnum::cases())->map(fn (GarrisonFormatEnum $f) => [
                'value' => $f->value,
                'label' => $f->label(),
                'description' => $f->description(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'garrison_id' => ['nullable', 'integer', 'exists:tos_garrisons,id'],
            'format' => ['nullable', Rule::enum(GarrisonFormatEnum::class)],
            // An Envoy is a second, different Allegiance (June 2026 errata).
            'envoy_allegiance_id' => ['nullable', 'integer', 'exists:tos_allegiances,id', 'different:allegiance_id'],
            'notes' => ['nullable', 'string'],
        ]);

        // If the user picked a Garrison, snap the allegiance + format to it
        // (the Builder UI already locks the pickers, but server-side
        // enforcement means an out-of-band POST can't desync them).
        if (! empty($validated['garrison_id'])) {
            $garrison = Garrison::where('id', $validated['garrison_id'])
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $validated['allegiance_id'] = $garrison->allegiance_id;
            $validated['format'] = $garrison->format->value;
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
            'allegiance.allegianceCards:id,allegiance_id,slug,name,image_path',
            // Envoy (second Allegiance) widens the hireable pool + asset pool.
            'envoyAllegiance:id,slug,name,type,secondary_type,color_slug',
            'envoyAllegiance.allegianceCards:id,allegiance_id,slug,name,image_path',
            'garrison:id,slug,name,format,allegiance_id',
            'companyUnits.unit:id,slug,name,title,scrip,combined_arms_child_id,restriction',
            'companyUnits.unit.specialUnitRules:id,slug,name',
            // Allegiances feed Asset::canAttachTo() when computing per-unit
            // attachability for the attach-asset picker.
            'companyUnits.unit.allegiances:id',
            // Sculpts include image columns now — the company-builder drawer
            // shows the FlipCard preview and lets the user switch sculpt
            // variants per company unit.
            'companyUnits.unit.sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
            'companyUnits.assets:id,slug,name,scrip_cost',
            'companyUnits.assets.limits',
            'stratagems:id,slug,name,tactical_cost,allegiance_id,allegiance_type',
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
            $hireableQuery = Unit::hireableFor($company->allegiance, $company->envoyAllegiance)
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
                    // hire_category drives the picker chip: Direct (Primary),
                    // Envoy (second Allegiance), or Neutral (restriction pool).
                    $direct = $u->allegiances->contains('id', $company->allegiance_id);
                    $envoy = ! $direct && $company->envoy_allegiance_id
                        && $u->allegiances->contains('id', $company->envoy_allegiance_id);
                    $u->setAttribute('hire_category', $direct ? 'direct' : ($envoy ? 'envoy' : 'neutral'));

                    return $u;
                });
        };

        $availableAssets = function () use ($company, $garrisonAssetIds) {
            // Assets matching the Primary or the Envoy Allegiance (or those
            // with no Allegiance restriction at all) are offered.
            $allegianceIds = array_filter([$company->allegiance_id, $company->envoy_allegiance_id]);
            $assetsQuery = Asset::query()
                ->where(function ($q) use ($allegianceIds) {
                    $q->whereDoesntHave('allegiances')
                        ->orWhereHas('allegiances', fn ($inner) => $inner->whereIn('tos_allegiances.id', $allegianceIds));
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

                    // Per-unit attachability (allegiance + non-Unique/non-Slot
                    // limits) so the attach drawer only offers Assets the
                    // targeted unit can actually take. Unique/slot collisions
                    // are still enforced at attach time.
                    $a->setAttribute(
                        'attachable_company_unit_ids',
                        $company->companyUnits
                            ->filter(fn ($cu) => $cu->unit && $a->canAttachTo($cu->unit))
                            ->pluck('id')
                            ->values()
                            ->all()
                    );

                    return $a;
                });
        };

        // Commanders eligible to lead: those carrying the Commander rule from
        // the Primary Allegiance, or from a Syndicate matching the Primary's
        // Type (rulebook p. 30). Drawn separately from the hire pool since a
        // Syndicate Commander need not be in the Primary/Envoy hire pool.
        $commanderPool = function () use ($company) {
            $primaryTypes = $company->allegiance->typeValues();

            return Unit::query()
                ->whereHas('specialUnitRules', fn ($q) => $q->where('slug', 'commander'))
                ->where(function ($q) use ($company, $primaryTypes) {
                    $q->whereHas('allegiances', fn ($a) => $a->where('tos_allegiances.id', $company->allegiance_id))
                        ->orWhereHas('allegiances', fn ($a) => $a->where('is_syndicate', true)
                            ->where(fn ($t) => $t->whereIn('type', $primaryTypes)->orWhereIn('secondary_type', $primaryTypes)));
                })
                ->notCombinedArmsChild()
                ->with([
                    'specialUnitRules:id,slug,name',
                    'sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
                ])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'title', 'scrip', 'restriction', 'combined_arms_child_id']);
        };

        // Stratagems eligible for the deck: those available to the Primary
        // Allegiance, plus the Envoy's (flagged so the UI can show/limit them).
        $availableStratagems = function () use ($company) {
            $cols = ['id', 'slug', 'name', 'tactical_cost', 'allegiance_id', 'allegiance_type'];

            $primary = Stratagem::availableTo($company->allegiance)
                ->orderBy('name')->get($cols)
                ->each(fn (Stratagem $s) => $s->setAttribute('deck_source', 'primary'));

            if (! $company->envoyAllegiance) {
                return $primary->values();
            }

            $primaryIds = $primary->pluck('id');
            $envoy = Stratagem::availableTo($company->envoyAllegiance)
                ->orderBy('name')->get($cols)
                ->reject(fn (Stratagem $s) => $primaryIds->contains($s->id))
                ->each(fn (Stratagem $s) => $s->setAttribute('deck_source', 'envoy'));

            return $primary->merge($envoy)->sortBy('name')->values();
        };

        return inertia('TOS/Companies/View', [
            'company' => $company,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
            'has_commander' => $company->hasCommander(),
            'commander_count' => $company->commanderCount(),
            'max_commanders' => $company->maxCommandersFielded(),
            'envoy_scrip_spent' => $company->envoyScripSpent(),
            'envoy_scrip_cap' => $company->envoyScripCap(),
            'available_stratagems' => $availableStratagems,
            'stratagem_deck_size' => Company::STRATAGEM_DECK_SIZE,
            'max_envoy_stratagems' => Company::MAX_ENVOY_STRATAGEMS,
            'commander_pool' => $commanderPool,
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
            'stratagems:id,slug,name,tactical_cost,allegiance_id,allegiance_type',
        ]);

        return inertia('TOS/Companies/Shared', [
            'company' => $company,
            'scrip_budget' => $company->scripBudget(),
            'scrip_spent' => $company->scripSpent(),
            'scrip_remaining' => $company->scripRemaining(),
        ]);
    }

    /**
     * A Commander is eligible if it belongs to the Company's Primary
     * Allegiance, or to a Syndicate whose Type matches the Primary's
     * (rulebook p. 30). Expects $unit->allegiances loaded.
     */
    private function commanderAllegianceEligible(Company $company, Unit $unit): bool
    {
        if ($unit->allegiances->contains('id', $company->allegiance_id)) {
            return true;
        }

        $primaryTypes = $company->allegiance->typeValues();

        foreach ($unit->allegiances as $allegiance) {
            if ($allegiance->is_syndicate && array_intersect($allegiance->typeValues(), $primaryTypes)) {
                return true;
            }
        }

        return false;
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

        if ($promotingCommander) {
            // Commanders have their own eligibility (rulebook p. 30): they must
            // carry the Commander rule and belong to the Primary Allegiance or
            // a Syndicate matching the Primary's Type.
            $unit->loadMissing(['specialUnitRules:id,slug', 'allegiances:id,is_syndicate,type,secondary_type']);
            if (! $unit->specialUnitRules->contains('slug', 'commander')) {
                return back()->withErrors(['unit_id' => "{$unit->name} is not a Commander."]);
            }
            if (! $this->commanderAllegianceEligible($company, $unit)) {
                return back()->withErrors(['unit_id' => "{$unit->name} must belong to your Allegiance or a matching Syndicate."]);
            }
            // Format commander cap. A single-Commander format swaps the existing
            // Commander out; multi-Commander formats reject once the cap is hit.
            $cap = $company->maxCommandersFielded();
            if ($cap > 1 && $company->commanderCount() >= $cap) {
                return back()->withErrors(['unit_id' => "This format allows only {$cap} Commanders — remove one first."]);
            }
        } else {
            // Reject hires the rules wouldn't allow — keeps the saved company
            // valid. Envoy units are limited to Squad units (handled by the scope).
            $isHireable = Unit::hireableFor($company->allegiance, $company->envoyAllegiance)
                ->where('tos_units.id', $unit->id)->exists();
            if (! $isHireable) {
                return back()->withErrors(['unit_id' => "{$unit->name} can't be hired into this Company's Allegiance or Envoy."]);
            }
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

            // Envoy spend cap (rulebook p. 30) — Envoy-sourced hires can't
            // exceed 50% of the total Scrip.
            if ($company->envoy_allegiance_id) {
                $unit->loadMissing('allegiances:id');
                if ($company->belongsToEnvoyOnly($unit) && $company->envoyScripSpent() + $cost > $company->envoyScripCap()) {
                    return back()->withErrors([
                        'unit_id' => "Envoy hires are capped at {$company->envoyScripCap()} Scrip (50% of your total).",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($company, $unit, $promotingCommander) {
            // Single-Commander formats swap: a new promotion demotes the
            // existing Commander. Multi-Commander formats keep both (the cap
            // was already enforced above).
            if ($promotingCommander && $company->maxCommandersFielded() === 1) {
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

        // Envoy spend cap (rulebook p. 30) — Envoy-sourced Assets count toward
        // the 50%-of-total ceiling alongside Envoy Units.
        if ($company->envoy_allegiance_id) {
            $asset->loadMissing('allegiances:id');
            if ($company->belongsToEnvoyOnly($asset) && $company->envoyScripSpent() + $cost > $company->envoyScripCap()) {
                return back()->withErrors([
                    'asset_id' => "Envoy hires are capped at {$company->envoyScripCap()} Scrip (50% of your total).",
                ]);
            }
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

    /**
     * Add a Stratagem to the Company's deck. Enforces the deck size (6), the
     * Allegiance/Envoy availability, and the two-Envoy-Stratagem cap (p. 13/30).
     */
    public function addStratagem(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompany($company);

        $validated = $request->validate([
            'stratagem_id' => ['required', 'integer', 'exists:tos_stratagems,id'],
        ]);

        $stratagem = Stratagem::findOrFail($validated['stratagem_id']);

        if ($company->stratagems()->count() >= Company::STRATAGEM_DECK_SIZE) {
            return back()->withErrors(['stratagem_id' => 'Your Stratagem Deck is full ('.Company::STRATAGEM_DECK_SIZE.' cards).']);
        }

        $availableToPrimary = Stratagem::availableTo($company->allegiance)->whereKey($stratagem->id)->exists();
        $availableToEnvoy = $company->envoyAllegiance
            && Stratagem::availableTo($company->envoyAllegiance)->whereKey($stratagem->id)->exists();

        if (! $availableToPrimary && ! $availableToEnvoy) {
            return back()->withErrors(['stratagem_id' => "{$stratagem->name} isn't available to your Allegiance or Envoy."]);
        }

        // Envoy-only Stratagems count against the two-card Envoy limit.
        if (! $availableToPrimary && $company->envoyStratagemCount() >= Company::MAX_ENVOY_STRATAGEMS) {
            return back()->withErrors(['stratagem_id' => 'You may include at most '.Company::MAX_ENVOY_STRATAGEMS.' Envoy Stratagems.']);
        }

        $company->stratagems()->syncWithoutDetaching([$stratagem->id]);

        return back();
    }

    public function removeStratagem(Company $company, Stratagem $stratagem): RedirectResponse
    {
        $this->authorizeCompany($company);

        $company->stratagems()->detach($stratagem->id);

        return back();
    }

    private function authorizeCompany(Company $company): void
    {
        abort_unless($company->user_id === Auth::id(), 403);
    }
}
