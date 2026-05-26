<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\TotemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for a Tier-3 Totem unlock (pg 52–53). Exact-match flip:
 * the leader rolls and adds the totem with matching flip value. Black Joker
 * → Sniveling Coward (replaceable later); Red Joker → Mini-Master (picks an
 * action from a master sharing the leader's keyword).
 */
class Totem extends Model
{
    /** @use HasFactory<TotemFactory> */
    use HasFactory;

    protected $table = 'totem_catalog';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_black_joker' => 'boolean',
            'is_red_joker' => 'boolean',
            'abilities' => 'array',
            'attack_actions' => 'array',
            'tactical_actions' => 'array',
            'special_replace_with_other_totem' => 'boolean',
            'is_mini_master' => 'boolean',
        ];
    }

    protected static function newFactory(): TotemFactory
    {
        return TotemFactory::new();
    }
}
