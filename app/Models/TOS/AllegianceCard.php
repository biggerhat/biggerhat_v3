<?php

namespace App\Models\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Traits\TOS\GeneratesTosSlug;
use Database\Factories\TOS\AllegianceCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperAllegianceCard
 */
class AllegianceCard extends Model
{
    /** @use HasFactory<AllegianceCardFactory> */
    use GeneratesTosSlug, HasFactory;

    protected $table = 'tos_allegiance_cards';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function casts(): array
    {
        return [
            'type' => AllegianceTypeEnum::class,
            'secondary_type' => AllegianceTypeEnum::class,
        ];
    }

    /**
     * Every type this card counts as. Single-type cards return a one-element
     * array; hybrid cards (those that print both Earth and Malifaux on the
     * face) return both. Mirrors `Allegiance::types()` so any consumer can
     * treat the card and its parent Allegiance with the same shape.
     *
     * @return array<int, AllegianceTypeEnum>
     */
    public function types(): array
    {
        $out = [$this->type];
        if ($this->secondary_type !== null && $this->secondary_type !== $this->type) {
            $out[] = $this->secondary_type;
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function typeValues(): array
    {
        return array_map(fn (AllegianceTypeEnum $t) => $t->value, $this->types());
    }

    protected static function newFactory(): AllegianceCardFactory
    {
        return AllegianceCardFactory::new();
    }

    /**
     * Manual cascade for the SQLite test environment — detaches all six
     * tier pivots (3 Standard + 3 Primary) when the card is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $card) {
            $card->abilities()->detach();
            $card->actions()->detach();
            $card->triggers()->detach();
            $card->primaryAbilities()->detach();
            $card->primaryActions()->detach();
            $card->primaryTriggers()->detach();
        });
    }

    public function allegiance(): BelongsTo
    {
        return $this->belongsTo(Allegiance::class, 'allegiance_id');
    }

    // ── Standard tier ──────────────────────────────────────────────────

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'tos_allegiance_card_ability', 'allegiance_card_id', 'ability_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_ability.sort_order');
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'tos_allegiance_card_action', 'allegiance_card_id', 'action_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_action.sort_order');
    }

    public function triggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'tos_allegiance_card_trigger', 'allegiance_card_id', 'trigger_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_trigger.sort_order');
    }

    // ── Primary tier ───────────────────────────────────────────────────

    public function primaryAbilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'tos_allegiance_card_primary_ability', 'allegiance_card_id', 'ability_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_primary_ability.sort_order');
    }

    public function primaryActions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'tos_allegiance_card_primary_action', 'allegiance_card_id', 'action_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_primary_action.sort_order');
    }

    public function primaryTriggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'tos_allegiance_card_primary_trigger', 'allegiance_card_id', 'trigger_id')
            ->withPivot('sort_order')
            ->orderBy('tos_allegiance_card_primary_trigger.sort_order');
    }
}
