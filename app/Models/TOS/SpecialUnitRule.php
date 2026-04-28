<?php

namespace App\Models\TOS;

use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\SpecialUnitRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperSpecialUnitRule
 */
class SpecialUnitRule extends Model
{
    /** @use HasFactory<SpecialUnitRuleFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_special_unit_rules';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): SpecialUnitRuleFactory
    {
        return SpecialUnitRuleFactory::new();
    }

    protected static function slugNeedsRandomSuffix(): bool
    {
        return false;
    }

    /**
     * Manual cascade for the SQLite test environment — detaches the units
     * pivot when a special rule is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $rule) {
            $rule->units()->detach();
        });
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'tos_unit_special_rule', 'special_unit_rule_id', 'unit_id')
            ->using(UnitSpecialRulePivot::class)
            ->withPivot('parameters');
    }
}
