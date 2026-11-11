<?php

namespace App\Models\Cooperative;

use App\Enums\CoopRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Cooperative Mode NP class card.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CoopRoleEnum $role
 * @property bool $is_unique
 * @property int $resistance
 * @property int|null $speed
 * @property int $max_health
 * @property int|null $elite_max_health
 * @property list<array{name: string, text: string}>|null $abilities
 * @property list<array<string, mixed>>|null $actions  kind attack|tactical, name, rg, skl, rst, tn, dmg, text, triggers[]
 * @property list<array{flip_min: int|null, flip_max: int|null, ideal_range: string, effects: list<string>}>|null $behavior
 * @property list<string>|null $target_preference
 * @property string|null $elite_modifications
 * @property bool $is_published
 * @property int $sort_order
 */
class CoopClass extends Model
{
    /** @use HasFactory<\Database\Factories\Cooperative\CoopClassFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role' => CoopRoleEnum::class,
            'is_unique' => 'boolean',
            'abilities' => 'array',
            'actions' => 'array',
            'behavior' => 'array',
            'target_preference' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Builder<self> $query */
    public function scopePublished($query): void
    {
        $query->where('is_published', true);
    }
}
