<?php

namespace App\Models\TOS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperCompanyUnit
 */
class CompanyUnit extends Model
{
    protected $table = 'tos_company_units';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_commander' => 'boolean',
            'is_combined_arms_child' => 'boolean',
        ];
    }

    /**
     * Manual cascade for the SQLite test environment — detaches the
     * Asset pivot when the row is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $unit) {
            $unit->assets()->detach();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'tos_company_unit_assets', 'company_unit_id', 'asset_id')->withTimestamps();
    }
}
