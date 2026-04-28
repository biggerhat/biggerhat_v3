<?php

namespace App\Models\TOS;

use App\Models\User;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    /**
     * Manual cascade for the SQLite test environment. Each child
     * CompanyUnit's own booted hook in turn detaches its Asset pivot.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $company) {
            $company->companyUnits()->get()->each->delete();
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

    public function companyUnits(): HasMany
    {
        return $this->hasMany(CompanyUnit::class)->orderBy('position');
    }

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
     * Total Scrip the Commander brings to the Company (rulebook p. 9).
     * Multiple-Commander setups sum (defensive — usually 1).
     */
    public function scripBudget(): int
    {
        $sum = 0;
        foreach ($this->loadedCompanyUnits() as $cu) {
            if ($cu->is_commander) {
                $sum += (int) ($cu->unit->scrip ?? 0);
            }
        }

        return $sum;
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
