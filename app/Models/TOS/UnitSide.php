<?php

namespace App\Models\TOS;

use App\Enums\TOS\UnitSideEnum;
use Database\Factories\TOS\UnitSideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperUnitSide
 */
class UnitSide extends Model
{
    /** @use HasFactory<UnitSideFactory> */
    use HasFactory;

    protected $table = 'tos_unit_sides';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'side' => UnitSideEnum::class,
        ];
    }

    protected static function newFactory(): UnitSideFactory
    {
        return UnitSideFactory::new();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'tos_unit_side_ability', 'unit_side_id', 'ability_id')
            ->withPivot('sort_order')
            ->orderBy('tos_unit_side_ability.sort_order');
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'tos_unit_side_action', 'unit_side_id', 'action_id')
            ->withPivot('sort_order')
            ->orderBy('tos_unit_side_action.sort_order');
    }
}
