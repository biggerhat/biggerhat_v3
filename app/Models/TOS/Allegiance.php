<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\AllegianceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'is_syndicate' => 'boolean',
        ];
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

    public function scopeOfType(Builder $query, AllegianceTypeEnum|string $type): Builder
    {
        $value = $type instanceof AllegianceTypeEnum ? $type->value : $type;

        return $query->where('type', $value);
    }
}
