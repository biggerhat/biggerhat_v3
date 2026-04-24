<?php

namespace App\Models\TOS;

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\UsageLimitEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\ActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAction
 */
class Action extends Model
{
    /** @use HasFactory<ActionFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_actions';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'is_piercing' => 'boolean',
            'is_accurate' => 'boolean',
            'is_area' => 'boolean',
            'usage_limit' => UsageLimitEnum::class,
        ];
    }

    protected static function newFactory(): ActionFactory
    {
        return ActionFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $action) {
            $action->triggers()->delete();
            $action->typeLinks()->delete();
            $action->unitSides()->detach();
        });
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class, 'action_id')->orderBy('sort_order');
    }

    public function unitSides(): BelongsToMany
    {
        return $this->belongsToMany(UnitSide::class, 'tos_unit_side_action', 'action_id', 'unit_side_id');
    }

    /**
     * The rows in tos_action_types. Prefer the `types` accessor below in
     * presentation code — it returns a Collection of ActionTypeEnum values.
     */
    public function typeLinks(): HasMany
    {
        return $this->hasMany(ActionTypeLink::class, 'action_id')->orderBy('sort_order');
    }

    /**
     * @return \Illuminate\Support\Collection<int, ActionTypeEnum>
     */
    public function getTypesAttribute(): \Illuminate\Support\Collection
    {
        return $this->typeLinks->pluck('type');
    }

    /**
     * @param  array<int, ActionTypeEnum>  $types
     */
    public function syncTypes(array $types): void
    {
        $this->typeLinks()->delete();
        foreach (array_values($types) as $i => $type) {
            $this->typeLinks()->create(['type' => $type->value, 'sort_order' => $i]);
        }
    }
}
