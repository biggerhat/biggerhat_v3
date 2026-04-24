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

    /**
     * Sculpts don't require a name — a Unit's default sculpt is often
     * anonymous. When no name is set, pre-populate slug from the parent
     * Unit's name so GeneratesTosSlug's `if ($model->slug) return` check
     * short-circuits, leaving `name` null as the admin intended.
     */
    protected static function booted(): void
    {
        static::creating(function (self $sculpt) {
            if (empty($sculpt->slug) && empty($sculpt->name) && $sculpt->unit_id) {
                $parentName = Unit::whereKey($sculpt->unit_id)->value('name');
                if ($parentName) {
                    $sculpt->slug = \Illuminate\Support\Str::slug($parentName).'-'.\Illuminate\Support\Str::random(4);
                }
            }
        });
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
