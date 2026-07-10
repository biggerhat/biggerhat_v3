<?php

namespace App\Models\TOS;

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\User;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\GarrisonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tournament-level pool a player declares before a Fields-of-Glory event.
 *
 * Distinct from Company: a Company is a single battlefield force assembled
 * around one Commander; a Garrison is the larger pool a player draws from
 * when building Companies between rounds. The validation profile (commander
 * cap, scrip ceiling, stratagem count, envoy count) is set by `format`.
 *
 * Envoys are stored as a pivot to `tos_allegiance_cards` because the
 * codebase folded the old standalone Envoy entity into the Allegiance Card
 * Primary tier (see drop migration 2026_04_29_120000). The pivot keeps the
 * "envoys" name so the rules language survives in the schema.
 *
 * @mixin IdeHelperGarrison
 */
class Garrison extends Model
{
    /** @use HasFactory<GarrisonFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_garrisons';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'format' => GarrisonFormatEnum::class,
            'is_public' => 'boolean',
        ];
    }

    protected static function newFactory(): GarrisonFactory
    {
        return GarrisonFactory::new();
    }

    /**
     * share_code on create + manual cascade on delete (mirrors Company).
     * SQLite test env runs without FK enforcement, so the model has to
     * detach pivots itself for the cascade to take effect.
     */
    protected static function booted(): void
    {
        static::creating(function (self $garrison) {
            if (! $garrison->share_code) {
                $garrison->share_code = Str::random(12);
            }
        });

        static::deleting(function (self $garrison) {
            $garrison->garrisonUnits()->get()->each->delete();
            $garrison->assets()->detach();
            $garrison->stratagems()->detach();
            $garrison->envoys()->detach();
            // SQLite test env runs without FK enforcement, so the
            // tos_companies.garrison_id `nullOnDelete` doesn't fire there.
            // Mirror it manually so a deleted Garrison degrades dependent
            // Companies to unrestricted rather than dangling them.
            Company::where('garrison_id', $garrison->id)->update(['garrison_id' => null]);
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

    public function garrisonUnits(): HasMany
    {
        return $this->hasMany(GarrisonUnit::class)->orderBy('position');
    }

    public function commanderUnits(): HasMany
    {
        return $this->hasMany(GarrisonUnit::class)->where('is_commander', true);
    }

    /**
     * Asset pool. Pivot carries `quantity`; total cost is sum(quantity * scrip_cost).
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'tos_garrison_assets', 'garrison_id', 'asset_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function stratagems(): BelongsToMany
    {
        return $this->belongsToMany(Stratagem::class, 'tos_garrison_stratagems', 'garrison_id', 'stratagem_id')
            ->withTimestamps();
    }

    /**
     * Envoy slot — see model docblock for why this is an Allegiance Card pivot.
     */
    public function envoys(): BelongsToMany
    {
        return $this->belongsToMany(AllegianceCard::class, 'tos_garrison_envoys', 'garrison_id', 'allegiance_card_id')
            ->withTimestamps();
    }

    // ── Format-driven helpers ────────────────────────────────────────────

    public function maxCommanders(): int
    {
        return $this->format->maxCommanders();
    }

    public function scripBudget(): int
    {
        return $this->format->scripBudget();
    }

    public function stratagemCount(): int
    {
        return $this->format->stratagemCount();
    }

    public function envoyCount(): int
    {
        return $this->format->envoyCount();
    }

    /**
     * Total Scrip spent on non-Commander Units + Asset pool. Commanders
     * are free in a Garrison context (the format's scrip budget is the
     * pool you spend on roster building, separate from the per-Company
     * Commander-derived budget at game time).
     */
    public function scripSpent(): int
    {
        $sum = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<int, GarrisonUnit> $units */
        $units = $this->relationLoaded('garrisonUnits')
            ? $this->garrisonUnits
            : $this->garrisonUnits()->with('unit:id,scrip')->get();
        foreach ($units as $gu) {
            if (! $gu->is_commander) {
                $sum += (int) ($gu->unit->scrip ?? 0);
            }
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Asset> $assets */
        $assets = $this->relationLoaded('assets')
            ? $this->assets
            : $this->assets()->get();
        foreach ($assets as $asset) {
            $qty = (int) ($asset->pivot->quantity ?? 1);
            $sum += $qty * (int) ($asset->scrip_cost ?? 0);
        }

        return $sum;
    }

    public function scripRemaining(): int
    {
        return $this->scripBudget() - $this->scripSpent();
    }

    /**
     * Rule violations against the active format. Returns a flat list of
     * human-readable strings — empty array means the Garrison is legal.
     * UI surfaces these in real time; FormRequest-level validation should
     * also call into this so server-side rejection messages match.
     *
     * @return array<int, string>
     */
    public function violations(): array
    {
        $violations = [];

        // Eager load what we need so each violation check stays cheap.
        $units = $this->relationLoaded('garrisonUnits')
            ? $this->garrisonUnits
            : $this->garrisonUnits()->with('unit:id,name,scrip')->get();

        $commanderCount = $units->where('is_commander', true)->count();
        if ($commanderCount > $this->maxCommanders()) {
            $violations[] = "Too many Commanders ({$commanderCount}/{$this->maxCommanders()}).";
        }

        if ($this->scripSpent() > $this->scripBudget()) {
            $over = $this->scripSpent() - $this->scripBudget();
            $violations[] = "Over Scrip budget by {$over} (used {$this->scripSpent()}/{$this->scripBudget()}).";
        }

        // Same-name cap — count *all* units sharing a name (commanders count
        // toward their own name's tally per the rulebook wording).
        $byName = [];
        foreach ($units as $gu) {
            $name = $gu->unit->name ?? null;
            if ($name === null) {
                continue;
            }
            $byName[$name] = ($byName[$name] ?? 0) + 1;
        }
        foreach ($byName as $name => $count) {
            if ($count > $this->maxCommanders()) {
                $violations[] = "Too many copies of \"{$name}\" ({$count}/{$this->maxCommanders()}).";
            }
        }

        $stratagemCount = $this->relationLoaded('stratagems')
            ? $this->stratagems->count()
            : $this->stratagems()->count();
        if ($stratagemCount > $this->stratagemCount()) {
            $violations[] = "Too many Stratagems ({$stratagemCount}/{$this->stratagemCount()}).";
        }

        $envoyTotal = $this->relationLoaded('envoys')
            ? $this->envoys->count()
            : $this->envoys()->count();
        if ($envoyTotal > $this->envoyCount()) {
            $violations[] = "Too many Envoys ({$envoyTotal}/{$this->envoyCount()}).";
        }

        return $violations;
    }

    public function isLegal(): bool
    {
        return $this->violations() === [];
    }
}
