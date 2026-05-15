<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\LootCard;
use Illuminate\Support\Str;

class LootDeckService
{
    /**
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
     * Reshuffle on empty draws cards in the discard back into the deck —
     * cards on Loot Markers stay out per the rulebook.
     */
    public function draw(Game $game): ?LootCard
    {
        $state = $this->stateOrInit($game);

        if (empty($state['deck'])) {
            if (empty($state['discard'])) {
                return null;
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

    public function attachToMember(GameCrewMember $member, LootCard $card, string $side): void
    {
        if (! in_array($side, ['a', 'b'], true)) {
            throw new \InvalidArgumentException('side must be a or b');
        }

        $upgrades = $member->attached_upgrades ?? [];
        $title = $side === 'a' ? ($card->title_a ?? null) : ($card->title_b ?? null);
        $effect = $side === 'a' ? ($card->effect_a ?? null) : ($card->effect_b ?? null);

        $upgrades[] = [
            // Offset the id space well above any real Upgrade id so the two
            // sets can coexist in attached_upgrades without UI collision.
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
     * Must be called BEFORE the member is updated — markers reference
     * the still-live attached_upgrades.
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

        $remaining = collect($member->attached_upgrades ?? [])
            ->reject(fn ($u) => is_array($u) && ! empty($u['loot_card_id']))
            ->values()
            ->all();
        $member->update(['attached_upgrades' => $remaining]);

        return $created;
    }

    /**
     * The new owner re-picks a side per the rulebook — the old owner's
     * choice doesn't carry.
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

    public function discardCard(Game $game, int $cardId): void
    {
        $state = $this->stateOrInit($game);
        $state['discard'][] = $cardId;
        $game->update(['loot_state' => $state]);
    }

    /**
     * Returns null when the card is already in play (live on a model or
     * sitting as a dropped marker). Otherwise pulls it out of the
     * deck/discard pool and returns the LootCard.
     */
    public function selectCard(Game $game, int $cardId): ?LootCard
    {
        $state = $this->stateOrInit($game);

        $removeFrom = function (array $pool) use ($cardId): array {
            $found = false;
            $remaining = [];
            foreach ($pool as $id) {
                if (! $found && (int) $id === $cardId) {
                    $found = true;

                    continue;
                }
                $remaining[] = $id;
            }

            return ['found' => $found, 'pool' => $remaining];
        };

        $deckResult = $removeFrom($state['deck']);
        if ($deckResult['found']) {
            $state['deck'] = $deckResult['pool'];
            $game->update(['loot_state' => $state]);

            return LootCard::find($cardId);
        }

        $discardResult = $removeFrom($state['discard']);
        if ($discardResult['found']) {
            $state['discard'] = $discardResult['pool'];
            $game->update(['loot_state' => $state]);

            return LootCard::find($cardId);
        }

        return null;
    }

    /**
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
