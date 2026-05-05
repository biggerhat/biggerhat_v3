<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\TOS\GarrisonFormatEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\Garrison;
use App\Models\TOS\GarrisonUnit;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * TOS Garrison Builder. A Garrison is the tournament-level pool a player
 * declares before a Fields-of-Glory event — Commanders, the Unit/Asset pool,
 * the Stratagem deck, and (when format permits) one Envoy slot. Validation
 * is driven by `GarrisonFormatEnum`; rule enforcement (caps + same-name
 * limit + scrip ceiling) lives on `Garrison::violations()`.
 *
 * Phase 2 ships read-only browse + the Create form + ownership-gated
 * metadata edits (rename / delete / publish toggle). Pool-modification
 * endpoints (addUnit, attachAsset, pickStratagem, pickEnvoy) land in
 * Phase 3 — same shape as `CompanyController` but operating on Garrison
 * pivots.
 */
class GarrisonController extends Controller
{
    public function index()
    {
        $garrisons = Garrison::query()
            ->where('user_id', Auth::id())
            ->with([
                'allegiance:id,slug,name,type,secondary_type,color_slug',
                'garrisonUnits:id,garrison_id,unit_id,is_commander',
            ])
            ->orderByDesc('updated_at')
            ->get();

        return inertia('TOS/Garrisons/Index', [
            'garrisons' => $garrisons,
        ]);
    }

    public function create()
    {
        return inertia('TOS/Garrisons/Create', [
            'allegiances' => fn () => Allegiance::query()
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'type', 'is_syndicate', 'color_slug']),
            'formats' => fn () => $this->formatOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'allegiance_id' => ['required', 'integer', 'exists:tos_allegiances,id'],
            'format' => ['required', 'string', Rule::in(array_column(GarrisonFormatEnum::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ]);

        $garrison = Garrison::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return redirect()->route('tos.garrisons.view', $garrison->slug);
    }

    public function view(Garrison $garrison)
    {
        $this->authorizeGarrison($garrison);

        return inertia('TOS/Garrisons/View', [
            ...$this->viewPayload($garrison),
            ...$this->pickerPayload($garrison),
            'format_options' => fn () => $this->formatOptions(),
        ]);
    }

    public function update(Request $request, Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'format' => ['required', 'string', Rule::in(array_column(GarrisonFormatEnum::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ]);

        $garrison->update($validated);

        return redirect()->route('tos.garrisons.view', $garrison->slug);
    }

    public function delete(Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $garrison->delete();

        return redirect()->route('tos.garrisons.index')->withMessage('Garrison deleted.');
    }

    /**
     * Print-ready PDF of the Garrison roster — mirrors the Company PDF flow
     * (DomPDF + Blade). Covers all four pool types so a TO can drop the
     * sheet on a desk before round 1. Owner-only; the public share view
     * can wire its own copy if downstream players want to print someone
     * else's published list.
     */
    public function downloadPdf(Garrison $garrison): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeGarrison($garrison);

        $garrison->load([
            'allegiance:id,slug,name,type',
            'garrisonUnits.unit:id,slug,name,title,scrip,restriction',
            'garrisonUnits.unit.specialUnitRules:id,slug,name',
            'assets:id,slug,name,scrip_cost',
            'assets.limits:id,asset_id,limit_type,parameter_value',
            'stratagems:id,slug,name,tactical_cost,effect,allegiance_id,allegiance_type',
            'stratagems.allegiance:id,slug,name',
            'envoys:id,slug,name,allegiance_id',
            'envoys.allegiance:id,slug,name',
        ]);

        $commanders = $garrison->garrisonUnits->where('is_commander', true)->values();
        $units = $garrison->garrisonUnits->where('is_commander', false)->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('PDF.Garrison', [
            'garrison' => $garrison,
            'format' => $garrison->format,
            'commanders' => $commanders,
            'units' => $units,
            'scrip_budget' => $garrison->scripBudget(),
            'scrip_spent' => $garrison->scripSpent(),
            'scrip_remaining' => $garrison->scripRemaining(),
            'violations' => $garrison->violations(),
        ]);

        return $pdf->stream(\Illuminate\Support\Str::slug($garrison->name).'.pdf');
    }

    /**
     * Owner-only flip between private and publicly-shareable. Mirrors
     * CompanyController::togglePublic — share URL stays stable across the
     * toggle so it's just access-gated, not regenerated.
     */
    public function togglePublic(Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $garrison->update(['is_public' => ! $garrison->is_public]);

        return back();
    }

    /**
     * Public read-only Garrison view, bound by share_code. Returns 404 when
     * the row is private — same outcome as a missing share code, so a
     * scraper can't tell "private" from "doesn't exist".
     */
    public function shared(string $share_code): \Inertia\Response
    {
        $garrison = Garrison::query()
            ->where('share_code', $share_code)
            ->where('is_public', true)
            ->firstOrFail();

        $payload = $this->viewPayload($garrison, includeOwner: true);

        return inertia('TOS/Garrisons/Shared', $payload);
    }

    // ── Pool modification ────────────────────────────────────────────────
    //
    // Each endpoint enforces the rules the same way Garrison::violations()
    // does — same source of truth, applied at write time so the saved
    // Garrison stays legal. The UI surfaces the budget meter and same-name
    // counts in real time so users rarely hit these errors, but the server
    // re-validates regardless.

    public function addUnit(Request $request, Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:tos_units,id'],
            'is_commander' => ['nullable', 'boolean'],
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $asCommander = ! empty($validated['is_commander']);

        $garrison->load(['garrisonUnits.unit:id,name,scrip', 'assets:id,scrip_cost']);

        if (! Unit::hireableInto($garrison->allegiance)->where('tos_units.id', $unit->id)->exists()) {
            return back()->withErrors(['unit_id' => "{$unit->name} can't be hired into this Allegiance."]);
        }

        // Same-name cap: total copies of a given unit name must not exceed
        // the format's max-Commander count (rulebook wording — "more units
        // with the same name than the number of Commanders").
        $existingSameName = $garrison->garrisonUnits
            ->filter(fn (GarrisonUnit $gu) => ($gu->unit->name ?? null) === $unit->name)
            ->count();
        if ($existingSameName + 1 > $garrison->maxCommanders()) {
            return back()->withErrors(['unit_id' => "{$unit->name} would exceed the Same-Name cap of {$garrison->maxCommanders()}."]);
        }

        if ($asCommander) {
            $cmdrCount = $garrison->garrisonUnits->where('is_commander', true)->count();
            if ($cmdrCount + 1 > $garrison->maxCommanders()) {
                return back()->withErrors(['unit_id' => "Commander cap reached ({$garrison->maxCommanders()})."]);
            }
        } else {
            $cost = (int) ($unit->scrip ?? 0);
            if ($cost > $garrison->scripRemaining()) {
                return back()->withErrors([
                    'unit_id' => "Hiring {$unit->name} ({$cost} Scrip) would exceed the Garrison's pool by ".($cost - $garrison->scripRemaining()).' Scrip.',
                ]);
            }
        }

        $position = (int) ($garrison->garrisonUnits()->max('position') ?? -1) + 1;

        GarrisonUnit::create([
            'garrison_id' => $garrison->id,
            'unit_id' => $unit->id,
            'is_commander' => $asCommander,
            'position' => $position,
        ]);

        return back();
    }

    public function removeUnit(Garrison $garrison, GarrisonUnit $garrisonUnit): RedirectResponse
    {
        $this->authorizeGarrison($garrison);
        abort_unless($garrisonUnit->garrison_id === $garrison->id, 404);

        $garrisonUnit->delete();

        return back();
    }

    /**
     * Persist which sculpt variant the user wants displayed for a Garrison
     * Unit slot. Mirrors `CompanyController::updateSculpt` — validates the
     * sculpt actually belongs to the unit so a bad ID can't slip through.
     */
    public function updateSculpt(Request $request, Garrison $garrison, GarrisonUnit $garrisonUnit): RedirectResponse
    {
        $this->authorizeGarrison($garrison);
        abort_unless($garrisonUnit->garrison_id === $garrison->id, 404);

        $validated = $request->validate([
            'sculpt_id' => ['nullable', 'integer', 'exists:tos_unit_sculpts,id'],
        ]);

        if ($validated['sculpt_id'] !== null) {
            $sculptUnitId = UnitSculpt::whereKey($validated['sculpt_id'])->value('unit_id');
            abort_unless($sculptUnitId === $garrisonUnit->unit_id, 422, 'That sculpt does not belong to this unit.');
        }

        $garrisonUnit->update(['sculpt_id' => $validated['sculpt_id']]);

        return back();
    }

    /**
     * Add an Asset to the pool, or bump its quantity if it's already in.
     * `delta` is +1 by default; the UI's quantity stepper passes ±1 to
     * the same endpoint. Total cost across all rows stays under budget.
     */
    public function attachAsset(Request $request, Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $validated = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:tos_assets,id'],
            'delta' => ['nullable', 'integer', 'min:-99', 'max:99'],
        ]);

        $asset = Asset::with('limits')->findOrFail($validated['asset_id']);
        $delta = (int) ($validated['delta'] ?? 1);
        if ($delta === 0) {
            return back();
        }

        $garrison->load(['garrisonUnits.unit:id,scrip', 'assets:id,scrip_cost']);

        // Allegiance gate: asset must be either unrestricted or attached to
        // this Garrison's Allegiance.
        $appliesToAlle = $asset->allegiances()->count() === 0
            || $asset->allegiances()->where('tos_allegiances.id', $garrison->allegiance_id)->exists();
        if (! $appliesToAlle) {
            return back()->withErrors(['asset_id' => "{$asset->name} isn't available to this Allegiance."]);
        }

        $existingQty = (int) ($garrison->assets()->where('tos_assets.id', $asset->id)->value('tos_garrison_assets.quantity') ?? 0);
        $newQty = $existingQty + $delta;
        if ($newQty < 0) {
            $newQty = 0;
        }

        // Scrip ceiling against the new total — only matters when delta
        // raises the cost (going down is always safe).
        if ($delta > 0) {
            $costDelta = $delta * (int) ($asset->scrip_cost ?? 0);
            if ($costDelta > $garrison->scripRemaining()) {
                return back()->withErrors([
                    'asset_id' => "Adding {$asset->name} (+{$costDelta} Scrip) would exceed the pool by ".($costDelta - $garrison->scripRemaining()).' Scrip.',
                ]);
            }
        }

        if ($newQty === 0) {
            $garrison->assets()->detach($asset->id);
        } elseif ($existingQty === 0) {
            $garrison->assets()->attach($asset->id, ['quantity' => $newQty]);
        } else {
            $garrison->assets()->updateExistingPivot($asset->id, ['quantity' => $newQty]);
        }

        return back();
    }

    public function detachAsset(Garrison $garrison, Asset $asset): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $garrison->assets()->detach($asset->id);

        return back();
    }

    public function pickStratagem(Request $request, Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $validated = $request->validate([
            'stratagem_id' => ['required', 'integer', 'exists:tos_stratagems,id'],
        ]);

        $stratagem = Stratagem::findOrFail($validated['stratagem_id']);

        // Applicability: specific allegiance match OR matching allegiance_type
        // for type-restricted stratagems (mirrors Stratagem::availableTo).
        if (! Stratagem::availableTo($garrison->allegiance)->where('tos_stratagems.id', $stratagem->id)->exists()) {
            return back()->withErrors(['stratagem_id' => "{$stratagem->name} isn't available to this Allegiance."]);
        }

        $current = $garrison->stratagems()->count();
        if ($current + 1 > $garrison->stratagemCount()) {
            return back()->withErrors(['stratagem_id' => "Stratagem cap reached ({$garrison->stratagemCount()})."]);
        }

        $garrison->stratagems()->syncWithoutDetaching([$stratagem->id]);

        return back();
    }

    public function unpickStratagem(Garrison $garrison, Stratagem $stratagem): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $garrison->stratagems()->detach($stratagem->id);

        return back();
    }

    public function pickEnvoy(Request $request, Garrison $garrison): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $validated = $request->validate([
            'allegiance_card_id' => ['required', 'integer', 'exists:tos_allegiance_cards,id'],
        ]);

        if ($garrison->envoyCount() === 0) {
            return back()->withErrors(['allegiance_card_id' => 'This format does not include an Envoy slot.']);
        }

        $card = AllegianceCard::findOrFail($validated['allegiance_card_id']);

        // Per Option 1: Envoys are this Allegiance's Allegiance Cards (the
        // Primary tier replaced the standalone Envoy entity). Foreign cards
        // get bounced.
        if ($card->allegiance_id !== $garrison->allegiance_id) {
            return back()->withErrors(['allegiance_card_id' => "{$card->name} isn't an Allegiance Card of this Allegiance."]);
        }

        $current = $garrison->envoys()->count();
        if ($current + 1 > $garrison->envoyCount()) {
            return back()->withErrors(['allegiance_card_id' => "Envoy slot already filled ({$garrison->envoyCount()})."]);
        }

        $garrison->envoys()->syncWithoutDetaching([$card->id]);

        return back();
    }

    public function unpickEnvoy(Garrison $garrison, AllegianceCard $allegianceCard): RedirectResponse
    {
        $this->authorizeGarrison($garrison);

        $garrison->envoys()->detach($allegianceCard->id);

        return back();
    }

    private function authorizeGarrison(Garrison $garrison): void
    {
        abort_if($garrison->user_id !== Auth::id(), 403);
    }

    /**
     * Single source of truth for the View / Shared payloads — both pages
     * render the same data; only the wrapping component changes.
     *
     * @return array<string, mixed>
     */
    private function viewPayload(Garrison $garrison, bool $includeOwner = false): array
    {
        $relations = [
            'allegiance:id,slug,name,type,secondary_type,color_slug',
            'garrisonUnits.unit:id,slug,name,title,scrip,restriction',
            'garrisonUnits.unit.specialUnitRules:id,slug,name',
            'garrisonUnits.unit.sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
            'assets:id,slug,name,scrip_cost,image_path',
            'assets.limits:id,asset_id,limit_type,parameter_type,parameter_value',
            'stratagems:id,slug,name,tactical_cost,effect,image_path,allegiance_id,allegiance_type',
            'stratagems.allegiance:id,slug,name,color_slug',
            'envoys:id,slug,name,image_path,allegiance_id',
            'envoys.allegiance:id,slug,name,color_slug',
        ];
        if ($includeOwner) {
            $relations[] = 'user:id,name';
        }
        $garrison->load($relations);

        return [
            'garrison' => $garrison,
            'format' => [
                'value' => $garrison->format->value,
                'label' => $garrison->format->label(),
                'description' => $garrison->format->description(),
                'max_commanders' => $garrison->maxCommanders(),
                'scrip_budget' => $garrison->scripBudget(),
                'stratagem_count' => $garrison->stratagemCount(),
                'envoy_count' => $garrison->envoyCount(),
            ],
            'scrip_spent' => $garrison->scripSpent(),
            'scrip_remaining' => $garrison->scripRemaining(),
            'violations' => $garrison->violations(),
        ];
    }

    /**
     * Pickable resource lists for the View page's Add drawers — hireable
     * Units + available Assets / Stratagems / Envoys, scoped to the
     * Garrison's Allegiance. Wrapped in lazy closures so partial reloads
     * (`router.reload({ only: [...] })`) skip recomputation when the user
     * is just adjusting the existing pool.
     *
     * @return array<string, callable(): mixed>
     */
    private function pickerPayload(Garrison $garrison): array
    {
        return [
            'hireable_units' => fn () => Unit::hireableInto($garrison->allegiance)
                ->notCombinedArmsChild()
                ->with([
                    'specialUnitRules:id,slug,name',
                    'sculpts:id,unit_id,slug,name,front_image,back_image,combination_image',
                    'allegiances:id',
                ])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'title', 'scrip', 'restriction', 'combined_arms_child_id']),
            'available_assets' => fn () => Asset::query()
                ->where(function ($q) use ($garrison) {
                    $q->whereDoesntHave('allegiances')
                        ->orWhereHas('allegiances', fn ($inner) => $inner->where('tos_allegiances.id', $garrison->allegiance_id));
                })
                ->with(['limits', 'allegiances:id,slug,name'])
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'scrip_cost', 'image_path']),
            'available_stratagems' => fn () => Stratagem::availableTo($garrison->allegiance)
                ->with('allegiance:id,slug,name')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'tactical_cost', 'effect', 'image_path', 'allegiance_id', 'allegiance_type']),
            'available_envoys' => fn () => AllegianceCard::query()
                ->where('allegiance_id', $garrison->allegiance_id)
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'image_path', 'allegiance_id']),
        ];
    }

    /**
     * @return array<int, array{name: string, value: string, description: string}>
     */
    private function formatOptions(): array
    {
        $out = [];
        foreach (GarrisonFormatEnum::cases() as $case) {
            $out[] = [
                'name' => $case->label(),
                'value' => $case->value,
                'description' => $case->description(),
            ];
        }

        return $out;
    }
}
