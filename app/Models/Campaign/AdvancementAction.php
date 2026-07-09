<?php

namespace App\Models\Campaign;

use App\Models\Action;
use App\Traits\Campaign\HasActionAbilityAdvancementShape;
use Database\Factories\Campaign\AdvancementActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tier 2 Action advancement (pg 44–49). Adds a new action to the leader.
 * 
 * A few "always available" rows are unconditionally selectable. The one
 * "Any Joker" row lets the player pick any action from a non-master/
 * non-totem model sharing a keyword (cost <= 10).
 *
 * @property array<string, mixed>|null $stat_block
 * @property int|null $action_id
 * @property bool $is_signature
 * @property-read Action|null $action
 * @mixin IdeHelperAdvancementAction
 */
class AdvancementAction extends Model
{
    use HasActionAbilityAdvancementShape {
        casts as private shapeCasts;
    }

    /** @use HasFactory<AdvancementActionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            ...$this->shapeCasts(),
            'stat_block' => 'array',
            // Bespoke rows only (pg 31) — see StoreAdvancementActionRequest.
            'is_signature' => 'boolean',
        ];
    }

    protected static function newFactory(): AdvancementActionFactory
    {
        return AdvancementActionFactory::new();
    }

    /**
     * The real, already-existing Action this row grants — set for rows that
     * reuse a named action from elsewhere in the game. Null for bespoke
     * campaign-only rows (whose stats live in stat_block) and for the Any
     * Joker row.
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
