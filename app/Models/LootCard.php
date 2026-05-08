<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperLootCard
 */
class LootCard extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Bonanza loot cards have two sides; relations are scoped by the pivot
     * `side` column ('a' | 'b'). Two parallel relations per kind keep the
     * code that consumes them simple — no ad-hoc filtering on the caller.
     */
    public function sideAActions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'loot_card_action')
            ->wherePivot('side', 'a')
            ->withPivot('is_signature_action', 'sort_order')
            ->orderByPivot('sort_order');
    }

    public function sideBActions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'loot_card_action')
            ->wherePivot('side', 'b')
            ->withPivot('is_signature_action', 'sort_order')
            ->orderByPivot('sort_order');
    }

    public function sideAAbilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'loot_card_ability')
            ->wherePivot('side', 'a')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function sideBAbilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'loot_card_ability')
            ->wherePivot('side', 'b')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function sideATriggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'loot_card_trigger')
            ->wherePivot('side', 'a')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function sideBTriggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'loot_card_trigger')
            ->wherePivot('side', 'b')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Replace this card's side-A or side-B action attachments. Pass an array
     * of `['action_id' => int, 'is_signature_action' => bool]`. Existing
     * pivot rows for that side are wiped first so callers can submit the
     * full new list rather than diffing.
     *
     * @param  array<int, array{action_id: int, is_signature_action?: bool}>  $entries
     */
    public function syncSideActions(string $side, array $entries): void
    {
        $this->actions()->wherePivot('side', $side)->detach();
        foreach (array_values($entries) as $i => $entry) {
            $this->actions()->attach($entry['action_id'], [
                'side' => $side,
                'is_signature_action' => (bool) ($entry['is_signature_action'] ?? false),
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * Replace this card's side-A or side-B ability/trigger attachments.
     *
     * @param  array<int, int>  $ids
     */
    public function syncSideAbilities(string $side, array $ids): void
    {
        $this->abilities()->wherePivot('side', $side)->detach();
        foreach (array_values($ids) as $i => $id) {
            $this->abilities()->attach($id, ['side' => $side, 'sort_order' => $i]);
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function syncSideTriggers(string $side, array $ids): void
    {
        $this->triggers()->wherePivot('side', $side)->detach();
        foreach (array_values($ids) as $i => $id) {
            $this->triggers()->attach($id, ['side' => $side, 'sort_order' => $i]);
        }
    }

    // Bare relations used by the side-scoped accessors above and by the sync
    // helpers — needed for `wherePivot('side', ...)->detach()` to resolve.
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'loot_card_action')
            ->withPivot('side', 'is_signature_action', 'sort_order');
    }

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'loot_card_ability')
            ->withPivot('side', 'sort_order');
    }

    public function triggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'loot_card_trigger')
            ->withPivot('side', 'sort_order');
    }
}
