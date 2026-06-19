<?php

namespace App\Models\TOS;

use App\Models\User;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A roster of Units hired around a single Allegiance — the rulebook calls
 * this a "Company". The earlier Phase 1 scaffolding stood it up under the
 * Malifaux-flavoured "Crew" name; tables, models, routes, and pages were
 * renamed to "Company" once the rulebook nomenclature was confirmed.
 *
 * @mixin IdeHelperCompany
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_companies';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'format' => \App\Enums\TOS\GarrisonFormatEnum::class,
        ];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    /**
     * Generate a stable share_code on create (mirrors `CrewBuild::booted`)
     * and run the manual cascade on delete for the SQLite test environment —
     * each child CompanyUnit's own booted hook detaches its Asset pivot.
     */
    protected static function booted(): void
    {
        static::creating(function (self $company) {
            if (! $company->share_code) {
                $company->share_code = Str::random(12);
            }
        });

        static::deleting(function (self $company) {
            $company->companyUnits()->get()->each->delete();
            $company->stratagems()->detach();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class);
    }

    /**
     * Optional second Allegiance taken as an Envoy (June 2026 errata — Envoy
     * Cards were folded into the Allegiance Card). Units/Assets/Stratagems of
     * the Envoy's Allegiance become hireable (subject to the Envoy spend cap),
     * and the Envoy Allegiance Card applies its Standard tier only.
     */
    public function envoyAllegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'envoy_allegiance_id');
    }

    /**
     * Optional tournament-Garrison this Company is being built out of.
     * When set, the Company Builder restricts its hiring pool to the
     * Garrison's declared Units + Assets — see CompanyController::view
     * + addUnit + attachAsset for the enforcement points. NULL means
     * unrestricted casual play.
     */
    public function garrison(): BelongsTo
    {
        return $this->belongsTo(Garrison::class);
    }

    public function companyUnits(): HasMany
    {
        return $this->hasMany(CompanyUnit::class)->orderBy('position');
    }

    /** The Company's Stratagem Deck (General + Primary + up to two Envoy). */
    public function stratagems(): BelongsToMany
    {
        return $this->belongsToMany(Stratagem::class, 'tos_company_stratagems', 'company_id', 'stratagem_id');
    }

    /** Rulebook deck size (p. 13). */
    public const STRATAGEM_DECK_SIZE = 6;

    /** Max Stratagems that may come from the Envoy's Allegiance (p. 30). */
    public const MAX_ENVOY_STRATAGEMS = 2;

    /**
     * The (single) commander row, if one has been picked. Maintained purely
     * by the application — we don't enforce uniqueness at the DB layer.
     */
    public function commanderUnit(): HasMany
    {
        return $this->hasMany(CompanyUnit::class)->where('is_commander', true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CompanyUnit>
     */
    private function loadedCompanyUnits(): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, CompanyUnit> $units */
        $units = $this->relationLoaded('companyUnits')
            ? $this->companyUnits
            : $this->companyUnits()->with(['unit:id,scrip', 'assets:id,scrip_cost'])->get();

        return $units;
    }

    /**
     * Total Scrip the Commander(s) bring to the Company (rulebook p. 9), plus
     * any flat format bonus (e.g. "One Commander +10 Scrip"). Multiple-
     * Commander formats (Two Commanders, Theater of War) sum every Commander.
     */
    public function scripBudget(): int
    {
        $sum = 0;
        foreach ($this->loadedCompanyUnits() as $cu) {
            if ($cu->is_commander) {
                $sum += (int) ($cu->unit->scrip ?? 0);
            }
        }

        return $sum + ($this->format?->scripBonus() ?? 0);
    }

    /**
     * How many Commanders this Company may field. Driven by the play format
     * (game size); defaults to 1 when no format has been chosen yet.
     */
    public function maxCommandersFielded(): int
    {
        return $this->format?->commandersFielded() ?? 1;
    }

    /** Current Commander count — used to gate adding another one. */
    public function commanderCount(): int
    {
        $count = 0;
        foreach ($this->loadedCompanyUnits() as $cu) {
            if ($cu->is_commander) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Total Scrip cost of every non-Commander Unit + every attached Asset.
     */
    public function scripSpent(): int
    {
        $sum = 0;
        foreach ($this->loadedCompanyUnits() as $cu) {
            if (! $cu->is_commander) {
                $sum += (int) ($cu->unit->scrip ?? 0);
            }
            // Asset scrip costs ride on the attaching company_unit regardless
            // of whether the unit itself is the Commander.
            if ($cu->relationLoaded('assets')) {
                foreach ($cu->assets as $asset) {
                    $sum += (int) ($asset->scrip_cost ?? 0);
                }
            }
        }

        return $sum;
    }

    /**
     * Remaining Scrip the Company can still spend before exceeding the
     * Commander's budget. Negative when over.
     */
    public function scripRemaining(): int
    {
        return $this->scripBudget() - $this->scripSpent();
    }

    /**
     * Scrip spent on Envoy-sourced hires — non-Commander Units and Assets that
     * belong to the Envoy Allegiance (and not the Primary). Rulebook p. 30
     * caps this at 50% of the total budget.
     */
    public function envoyScripSpent(): int
    {
        if (! $this->envoy_allegiance_id) {
            return 0;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, CompanyUnit> $units */
        $units = $this->companyUnits()
            ->with(['unit:id,scrip', 'unit.allegiances:id', 'assets:id,scrip_cost', 'assets.allegiances:id'])
            ->get();

        $sum = 0;
        foreach ($units as $cu) {
            if (! $cu->is_commander && $this->belongsToEnvoyOnly($cu->unit)) {
                $sum += (int) ($cu->unit->scrip ?? 0);
            }
            foreach ($cu->assets as $asset) {
                if ($this->belongsToEnvoyOnly($asset)) {
                    $sum += (int) ($asset->scrip_cost ?? 0);
                }
            }
        }

        return $sum;
    }

    /** Half the total budget (rounded down) — the Envoy spend ceiling. */
    public function envoyScripCap(): int
    {
        return intdiv($this->scripBudget(), 2);
    }

    /**
     * How many deck Stratagems are Envoy-sourced — i.e. in the deck but not
     * available to the Primary Allegiance (so they're there via the Envoy).
     * Capped at MAX_ENVOY_STRATAGEMS.
     */
    public function envoyStratagemCount(): int
    {
        if (! $this->envoy_allegiance_id) {
            return 0;
        }

        $primaryIds = Stratagem::availableTo($this->allegiance)->pluck('id');

        return $this->stratagems()->whereNotIn('tos_stratagems.id', $primaryIds)->count();
    }

    /**
     * Whether a Unit/Asset (with `allegiances` loaded) is sourced from the
     * Envoy — i.e. in the Envoy Allegiance but not the Primary.
     *
     * @param  Unit|Asset|null  $model
     */
    public function belongsToEnvoyOnly($model): bool
    {
        if (! $model || ! $this->envoy_allegiance_id || ! $model->relationLoaded('allegiances')) {
            return false;
        }

        return $model->allegiances->contains('id', $this->envoy_allegiance_id)
            && ! $model->allegiances->contains('id', $this->allegiance_id);
    }

    /**
     * Whether the Company currently has a Commander assigned. Companies
     * without one aren't "playable" — the View page surfaces this as a
     * banner.
     */
    public function hasCommander(): bool
    {
        foreach ($this->loadedCompanyUnits() as $cu) {
            if ($cu->is_commander) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any company_unit in this Company has the given Asset attached.
     * Used to enforce the per-Company Unique cap — the rulebook p. 12
     * Unique limit means the Asset can only appear once across the whole
     * Company, not just once per Unit.
     */
    public function hasAssetAttached(Asset $asset): bool
    {
        $units = $this->loadedCompanyUnits();
        $units->loadMissing('assets:id');

        foreach ($units as $cu) {
            if ($cu->assets->contains('id', $asset->id)) {
                return true;
            }
        }

        return false;
    }
}
