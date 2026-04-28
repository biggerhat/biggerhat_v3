<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\AllegianceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperAllegiance
 */
class Allegiance extends Model
{
    /** @use HasFactory<AllegianceFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_allegiances';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'type' => AllegianceTypeEnum::class,
            'secondary_type' => AllegianceTypeEnum::class,
            'is_syndicate' => 'boolean',
        ];
    }

    /**
     * Manual cascades for the SQLite test environment, which runs without
     * FK enforcement (.env.testing DB_FOREIGN_KEYS=false). Production
     * relies on the migration's `cascadeOnDelete` FKs; this hook makes
     * sure tests delete dependent rows the same way.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $allegiance) {
            $allegiance->units()->detach();
            $allegiance->allegianceCards()->get()->each->delete();
            $allegiance->envoys()->get()->each->delete();
            $allegiance->stratagems()->get()->each->delete();
        });
    }

    public function allegianceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AllegianceCard::class);
    }

    public function envoys(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Envoy::class);
    }

    public function stratagems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Stratagem::class);
    }

    /**
     * Every type this Allegiance counts as. Single-type Allegiances return
     * a one-element array; hybrid Allegiances (those that print both Earth
     * and Malifaux on their card) return both. Used by every scope that
     * needs to OR-match a type-derived check (Neutral pool, Stratagem
     * applicability, Envoy restriction).
     *
     * @return array<int, AllegianceTypeEnum>
     */
    public function types(): array
    {
        $out = [$this->type];
        if ($this->secondary_type !== null && $this->secondary_type !== $this->type) {
            $out[] = $this->secondary_type;
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function typeValues(): array
    {
        return array_map(fn (AllegianceTypeEnum $t) => $t->value, $this->types());
    }

    protected static function newFactory(): AllegianceFactory
    {
        return AllegianceFactory::new();
    }

    protected static function slugNeedsRandomSuffix(): bool
    {
        return false;
    }

    public function scopeSyndicates(Builder $query): Builder
    {
        return $query->where('is_syndicate', true);
    }

    public function scopeMainAllegiances(Builder $query): Builder
    {
        return $query->where('is_syndicate', false);
    }

    /**
     * Allegiances that count as the given type — primary OR secondary. A
     * hybrid Allegiance with `type=earth, secondary_type=malifaux` is
     * returned by both `ofType('earth')` and `ofType('malifaux')` so it
     * appears on both Type-pool browse pages without the caller needing to
     * query twice.
     */
    public function scopeOfType(Builder $query, AllegianceTypeEnum|string $type): Builder
    {
        $value = $type instanceof AllegianceTypeEnum ? $type->value : $type;

        return $query->where(function (Builder $q) use ($value) {
            $q->where('type', $value)->orWhere('secondary_type', $value);
        });
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'tos_allegiance_unit', 'allegiance_id', 'unit_id');
    }
}
