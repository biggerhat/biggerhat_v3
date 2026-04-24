<?php

namespace App\Models\TOS;

use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\UnitSculptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUnitSculpt
 */
class UnitSculpt extends Model
{
    /** @use HasFactory<UnitSculptFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_unit_sculpts';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'release_date' => 'date',
        ];
    }

    protected static function newFactory(): UnitSculptFactory
    {
        return UnitSculptFactory::new();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
