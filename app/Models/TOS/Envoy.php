<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\EnvoyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperEnvoy
 */
class Envoy extends Model
{
    /** @use HasFactory<EnvoyFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_envoys';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'restriction' => EnvoyRestrictionEnum::class,
        ];
    }

    protected static function newFactory(): EnvoyFactory
    {
        return EnvoyFactory::new();
    }

    /**
     * Manual cascade for the SQLite test environment (no FK enforcement).
     */
    protected static function booted(): void
    {
        static::deleting(function (self $envoy) {
            $envoy->abilities()->detach();
        });
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'tos_envoy_ability', 'envoy_id', 'ability_id')
            ->withPivot('sort_order')
            ->orderBy('tos_envoy_ability.sort_order');
    }

    /**
     * Envoys are hireable into an Allegiance whose type matches the Envoy's
     * Restriction (rulebook p. 8: "These Envoys can only be taken if the
     * Allegiance matches the Restriction"). The "other" restriction value is
     * bespoke — never matches automatically.
     */
    public function scopeHireableInto(Builder $query, Allegiance $target): Builder
    {
        // A hybrid Allegiance can hire Envoys keyed to either of its types,
        // so the match runs across all of `Allegiance::types()`.
        $restrictions = array_map(fn (AllegianceTypeEnum $t) => match ($t) {
            AllegianceTypeEnum::Earth => EnvoyRestrictionEnum::Earth->value,
            AllegianceTypeEnum::Malifaux => EnvoyRestrictionEnum::Malifaux->value,
        }, $target->types());

        return $query->whereIn('restriction', $restrictions);
    }
}
