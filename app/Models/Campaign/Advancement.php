<?php

namespace App\Models\Campaign;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared shape for the four flip-based Leader advancement tables (Tier 1
 * Attack Mod, Tier 1 Tactical Mod, Tier 2 Action, Tier 2 Ability — pages
 * 38–51). Each concrete subclass sets its `$table` to the matching
 * physical table; everything else (casts, factory wiring) is uniform.
 *
 * Tier 3 Totem and Tier 3 Summoning have unique mechanics and live as
 * their own models.
 *
 * @property int $id
 * @property string $name
 * @property string $body
 * @property int|null $flip_value
 * @property bool $is_always_available
 * @property bool $is_black_joker
 * @property bool $is_red_joker
 * @property string $modifier_type
 * @property string|null $suit
 * @property int|null $skl_from
 * @property int|null $skl_to
 * @property bool $grants_signature
 * @property bool $joker_freechoice
 * @property array<string, mixed>|null $stat_block
 * @property string|null $defensive_ability_type
 */
abstract class Advancement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_always_available' => 'boolean',
            'is_black_joker' => 'boolean',
            'is_red_joker' => 'boolean',
            'grants_signature' => 'boolean',
            'joker_freechoice' => 'boolean',
            'stat_block' => 'array',
        ];
    }
}
