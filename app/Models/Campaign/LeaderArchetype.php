<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\LeaderArchetypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for one of the five M4E Campaign Mode Leader archetypes
 * (rulebook pg 17). Read-mostly: the Leader Builder pulls stat baselines and
 * action/ability cost caps from here.
 */
class LeaderArchetype extends Model
{
    /** @use HasFactory<LeaderArchetypeFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'attack_gets_trigger' => 'boolean',
        ];
    }

    protected static function newFactory(): LeaderArchetypeFactory
    {
        return LeaderArchetypeFactory::new();
    }
}
