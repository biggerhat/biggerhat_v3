<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\LootCard;
use Illuminate\Support\Str;

/**
 * Centralized state machine for the Bonanza Brawl loot deck. Owns every
 * mutation of `Game.loot_state` so the JSON shape stays consistent and the
 * race-prone bits (deck pop, reshuffle, marker create/destroy) live in one
 * spot rather than smeared across controllers.
 */
class LootDeckService
{
    /**
     * Initial loot_state for a fresh Bonanza game. Shuffles every catalog
     * card_id into the deck, leaves discard + markers empty. Idempotent —
     * callers pass the existing state if there is one and we skip if it
     * already looks initialized (a re-trigger shouldn't reset mid-game).
     *
     * @return array{deck: array<int>, discard: array<int>, dropped_markers: array<int, array<string, mixed>>}
     */
    public function initialState(): array
    {
        $ids = LootCard::query()->orderBy('id')->pluck('id')->all();
        shuffle($ids);

        return [
            'deck' => $ids,
            'discard' => [],
            'dropped_markers' => [],
        ];
    }

    /**
     * Pop the top of the deck (array tail = top, so a single array_pop is
     * O(1) and avoids reindexing). When the deck runs dry, reshuffle any
     * cards in the discard pile back into the deck — the rulebook spec
     * explicitly excludes cards currently designated to a Loot Marker.
     */
    public function draw(Game $game): ?LootCard
    {
        $state = $this->stateOrInit($game);

        if (empty($state['deck'])) {
            if (empty($state['discard'])) {
                return null; // entire deck still bound to live markers
            }
            $state['deck'] = $state['discard'];
            shuffle($state['deck']);
            $state['discard'] = [];
        }

        $cardId = array_pop($state['deck']);
        $state['deck'] = array_values($state['deck']);
        $game->update(['loot_state' => $state]);

        return LootCard::find($cardId);
    }

    /**
     * Attach a previously-drawn card to a crew member with a chosen side.
     * The attached_upgrades JSON gets a new entry tagged with `loot_card_id`
     * + `loot_side` so the UI can render it differently from regular crew
     * upgrades and the drop-on-kill flow can find it later.
     *
     * Discards aren't touched here — the card stays "live" on the model
     * until a marker is dropped on death.
     */
    public function attachToMember(GameCrewMember $member, LootCard $card, string $side): void
    {
        if (! in_array($side, ['a', 'b'], true)) {
            throw new \InvalidArgumentException('side must be a or b');
        }

        $upgrades = $member->attached_upgrades ?? [];
        $title = $side === 'a' ? ($card->title_a ?? null) : ($card->title_b ?? null);
        $effect = $side === 'a' ? ($card->effect_a ?? null) : ($card->effect_b ?? null);

        $upgrades[] = [
            // `id` namespace is shared with regular upgrades but loot_card_id
            // is what the loot subsystem keys on; pick a high-bit-friendly
            // numeric so a future regular Upgrade with the same id can't
            // collide visually in the UI.
            'id' => 1_000_000_000 + (int) $card->id,
            'name' => $title ? "{$card->name} — {$title}" : $card->name,
            'front_image' => $card->image,
            'back_image' => null,
            'loot_card_id' => $card->id,
            'loot_side' => $side,
            'loot_effect' => $effect,
        ];

        $member->update(['attached_upgrades' => $upgrades]);
    }

    /**
     * Drop a Loot Marker for every loot card currently attached to a member
     * being killed. Called from killCrewMember before the member is updated
     * so the markers reference the still-live attached_upgrades. Each marker
     * gets a stable id (uuid) so the front-end can address it via Yoink.
     *
     * Returns the list of created marker entries for any UI feedback the
     * caller wants to show ("3 loot markers dropped").
     *
     * @return array<int, array<string, mixed>>
     */
    public function dropMarkersOnDeath(Game $game, GameCrewMember $member): array
    {
        $lootEntries = collect($member->attached_upgrades ?? [])
            ->filter(fn ($u) => is_array($u) && ! empty($u['loot_card_id']))
            ->values();

        if ($lootEntries->isEmpty()) {
            return [];
        }

        $state = $this->stateOrInit($game);
        $created = [];

        foreach ($lootEntries as $entry) {
            $marker = [
                'id' => (string) Str::uuid(),
                'card_id' => (int) $entry['loot_card_id'],
                'side' => $entry['loot_side'] ?? 'a',
                'dropped_by_player_id' => $member->game_player_id,
            ];
            $state['dropped_markers'][] = $marker;
            $created[] = $marker;
        }

        $game->update(['loot_state' => $state]);

        // Strip the loot entries from the dying member — they're now markers
        // on the table, not on the model. Leave non-loot upgrades alone.
        $remaining = collect($member->attached_upgrades ?? [])
            ->reject(fn ($u) => is_array($u) && ! empty($u['loot_card_id']))
            ->values()
            ->all();
        $member->update(['attached_upgrades' => $remaining]);

        return $created;
    }

    /**
     * Yoink: claim a dropped marker, removing it from the table and attaching
     * the underlying card (with a freshly-chosen side) to the looter. The
     * old side selection from the previous owner doesn't carry — Yoink is
     * a fresh attach, the rulebook lets the new owner pick again.
     */
    public function yoinkMarker(Game $game, GameCrewMember $member, string $markerId, string $side): ?LootCard
    {
        $state = $this->stateOrInit($game);

        $idx = collect($state['dropped_markers'] ?? [])
            ->search(fn ($m) => ($m['id'] ?? null) === $markerId);
        if ($idx === false) {
            return null;
        }

        $marker = $state['dropped_markers'][$idx];
        unset($state['dropped_markers'][$idx]);
        $state['dropped_markers'] = array_values($state['dropped_markers']);
        $game->update(['loot_state' => $state]);

        $card = LootCard::find($marker['card_id']);
        if ($card) {
            $this->attachToMember($member, $card, $side);
        }

        return $card;
    }

    /**
     * Move a previously-attached loot card to the discard pile. Used when
     * a card is detached without the model dying (e.g. dealer override).
     */
    public function discardCard(Game $game, int $cardId): void
    {
        $state = $this->stateOrInit($game);
        $state['discard'][] = $cardId;
        $game->update(['loot_state' => $state]);
    }

    /**
     * Read-or-init helper. Centralizes the "loot_state defaults to empty
     * shape if null/garbage" guard so callers don't repeatedly null-check.
     *
     * @return array{deck: array<int>, discard: array<int>, dropped_markers: array<int, array<string, mixed>>}
     */
    private function stateOrInit(Game $game): array
    {
        $state = $game->loot_state ?? [];

        return [
            'deck' => $state['deck'] ?? [],
            'discard' => $state['discard'] ?? [],
            'dropped_markers' => $state['dropped_markers'] ?? [],
        ];
    }
}
