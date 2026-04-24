<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\StratagemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperStratagem
 */
class Stratagem extends Model
{
    /** @use HasFactory<StratagemFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_stratagems';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'allegiance_type' => AllegianceTypeEnum::class,
        ];
    }

    protected static function newFactory(): StratagemFactory
    {
        return StratagemFactory::new();
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    /**
     * A player's Stratagem Deck may include any Stratagem whose listed
     * Allegiance matches the chosen Allegiance, OR — when the Stratagem lists
     * an Allegiance Type instead of a specific Allegiance — whose Type matches
     * (rulebook p. 13). Specific-Allegiance beats type: if `allegiance_id` is
     * set it's the sole matcher, so "Cult-only" doesn't accidentally show up
     * for Gibbering Hordes via the type fallback.
     */
    public function scopeAvailableTo(Builder $query, Allegiance $target): Builder
    {
        return $query->where(function ($q) use ($target) {
            $q->where('allegiance_id', $target->id)
                ->orWhere(function ($qq) use ($target) {
                    $qq->whereNull('allegiance_id')->where('allegiance_type', $target->type->value);
                });
        });
    }
}
