<?php

namespace App\Services\Fate;

use App\Enums\FateZoneEnum;
use App\Exceptions\FateException;
use App\Models\Game;
use App\Models\GameFateCard;
use App\Models\GameFateFlip;
use App\Models\GameFatePrompt;
use App\Models\GameLog;
use App\Models\GamePlayer;
use App\Models\User;
use App\Support\Fate\FateCards;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Each player's 54-card Fate deck for a full game, kept on the server.
 *
 * - The server shuffles and deals (`random_int`); a client never picks or sees a card it is not entitled to. The deck's order never
 *   leaves this class: a client is told only what an effect lets it see (the cards it peeked at, a card it searched out).
 * - Every action runs in one transaction under a lock on the game row and re-checks that each card is where the request said it was.
 * - A hand is returned only to the person who controls that player (state()); the opponent and observers get its size.
 * - Every action writes a game log row. Public cards are named in it (the discard pile and the conflict are face up); a hidden card
 *   never is, only how many.
 *
 * The first rules text lives in docs/plans/fate-deck-and-board-badges.md (CRG p.6-10, 31-32, 43, 48, 63).
 */
class FateDeckService
{
    /** The most cards one draw or one look at the top of the deck may take. */
    public const MAX_AT_ONCE = 20;

    // ---------------------------------------------------------------------------------------------------------------------------
    // What may be seen
    // ---------------------------------------------------------------------------------------------------------------------------

    /**
     * The most cards a hand holds in this game: 7, or less in the Gaining Grounds modes that say so (a tournament game takes it from its
     * event's encounter type).
     */
    public function handMax(Game $game): int
    {
        $type = $game->tournamentGame?->round?->tournament?->encounter_type;

        return FateCards::handMaxFor($type?->value);
    }

    /**
     * The players in a game that this user controls: their own slot, or both slots of a solo game they created. An observer controls none.
     *
     * @return list<int> game_player ids
     */
    public function controlledPlayerIds(Game $game, ?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $game->players()
            ->get(['id', 'user_id'])
            ->filter(fn (GamePlayer $p) => $p->user_id === $user->id || ($game->is_solo && $game->creator_id === $user->id))
            ->pluck('id')
            ->all();
    }

    /**
     * What a viewer may know. The deck is only a size. A hand is listed only for players the viewer controls; the others show a count.
     * The discard pile, the conflict and the removed cards are face up for everyone.
     *
     * @param  list<int>  $controlled  game_player ids the viewer controls
     * @return array<string, mixed>
     */
    public function state(Game $game, array $controlled): array
    {
        /** @var Collection<int, GameFateCard> $cards */
        $cards = GameFateCard::query()->where('game_id', $game->id)->orderBy('position')->get();
        $flips = GameFateFlip::query()->where('game_id', $game->id)->whereNull('resolved_at')->orderBy('id')->get();
        $prompts = GameFatePrompt::query()->where('game_id', $game->id)->whereNull('dismissed_at')->orderBy('id')->get();
        $players = $game->players()->orderBy('slot')->get(['id', 'slot']);
        $handMax = $this->handMax($game);
        $slotOf = $players->pluck('slot', 'id');
        $byRow = $cards->keyBy('id');

        return [
            'started' => $cards->isNotEmpty(),
            'hand_max' => $handMax,
            'players' => $players->map(function (GamePlayer $p) use ($cards, $controlled, $handMax) {
                $mine = $cards->where('game_player_id', $p->id);
                $hand = $mine->where('zone', FateZoneEnum::Hand);

                return [
                    'slot' => $p->slot,
                    'player_id' => $p->id,
                    'deck_count' => $mine->where('zone', FateZoneEnum::Deck)->count(),
                    'hand_count' => $hand->count(),
                    'over_max' => max(0, $hand->count() - $handMax),
                    'discard' => $mine->where('zone', FateZoneEnum::Discard)->pluck('card')->values()->all(),
                    'removed' => $mine->where('zone', FateZoneEnum::Removed)->pluck('card')->values()->all(),
                    // a hand is the one hidden thing here: only its controller is given the cards
                    ...(in_array($p->id, $controlled, true) ? ['hand' => $hand->pluck('card')->values()->all()] : []),
                ];
            })->values()->all(),
            'conflict' => $cards->where('zone', FateZoneEnum::Conflict)->map(fn (GameFateCard $c) => [
                'flip_id' => $c->flip_id,
                'owner_slot' => $slotOf[$c->game_player_id] ?? null,
                'card' => $c->card,
                'joker_suit' => $c->joker_suit,
            ])->values()->all(),
            'flips' => $flips->map(function (GameFateFlip $f) use ($cards, $byRow, $slotOf, $flips) {
                $revealed = $cards->where('flip_id', $f->id)->where('zone', FateZoneEnum::Conflict);
                $chosen = $f->chosen_id ? $byRow->get($f->chosen_id) : null;

                return [
                    'id' => $f->id,
                    'owner_slot' => $slotOf[$f->game_player_id] ?? null,
                    'modifier' => $f->modifier,
                    'label' => $f->label,
                    'subject' => $f->subject,
                    'chosen' => $chosen ? ['owner_slot' => $slotOf[$chosen->game_player_id] ?? null, 'card' => $chosen->card, 'joker_suit' => $chosen->joker_suit] : null,
                    // which revealed cards may be taken as the result (nothing is offered once one has been)
                    'legal' => $chosen ? [] : self::legalChoices($f->modifier, $revealed->pluck('card')->all()),
                    'cheaters' => collect($f->cheaters ?? [])->map(fn ($id) => $slotOf[$id] ?? null)->filter()->values()->all(),
                    'duel' => $this->duelState($f, $flips->keyBy('id'), $slotOf),
                ];
            })->values()->all(),
            // what the other player has asked of me (a request is addressed to one player, and only they are shown it)
            'prompts' => $prompts->where('status', 'pending')->filter(fn (GameFatePrompt $p) => in_array($p->to_player_id, $controlled, true))->map(fn (GameFatePrompt $p) => [
                'id' => $p->id,
                'from_slot' => $slotOf[$p->from_player_id] ?? null,
                'to_slot' => $slotOf[$p->to_player_id] ?? null,
                'kind' => $p->kind,
                'count' => $p->count,
                'note' => $p->note,
            ])->values()->all(),
            // what I have asked that is still waiting
            'sent' => $prompts->where('status', 'pending')->filter(fn (GameFatePrompt $p) => in_array($p->from_player_id, $controlled, true))->map(fn (GameFatePrompt $p) => [
                'id' => $p->id,
                'to_slot' => $slotOf[$p->to_player_id] ?? null,
                'kind' => $p->kind,
                'count' => $p->count,
            ])->values()->all(),
            // a hand someone chose to show me: only the player who asked is given the cards
            'answers' => $prompts->where('kind', GameFatePrompt::REVEAL)->where('status', '!=', 'pending')->filter(fn (GameFatePrompt $p) => in_array($p->from_player_id, $controlled, true))->map(fn (GameFatePrompt $p) => [
                'id' => $p->id,
                'owner_slot' => $slotOf[$p->to_player_id] ?? null,
                'declined' => $p->status === 'declined',
                'cards' => $p->status === 'done' ? $p->cards : null,
            ])->values()->all(),
        ];
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Drawing and discarding
    // ---------------------------------------------------------------------------------------------------------------------------

    /**
     * Draw cards into the hand. A draw is not capped at the hand maximum (an effect may draw past it); the state reports `over_max` and the
     * player discards down (CRG p.7). If the deck runs out, the discard pile is shuffled in and the draw carries on (CRG p.7).
     *
     * @return list<int> the cards drawn: the owner's alone to know
     */
    public function draw(GamePlayer $player, int $n): array
    {
        $n = $this->count($n);

        return $this->locked($player, function () use ($player, $n) {
            $drawn = $this->drawInto($player, $n);
            $this->log($player, 'fate.draw', ['count' => count($drawn)]);

            return $drawn;
        });
    }

    /**
     * Start phase (CRG p.31): discard any number of cards from the hand (they are shown), then draw until the hand is at the maximum.
     *
     * `$max` is a lower hand limit than the game's (a master or effect that shrinks the hand); it can never raise it. `$shuffle` puts the
     * discard pile back into the deck, shuffled, after the discards and before the draw.
     *
     * @param  list<int>  $discard
     * @return list<int> the cards drawn
     */
    public function refill(GamePlayer $player, array $discard, ?int $max = null, bool $shuffle = false): array
    {
        return $this->locked($player, function () use ($player, $discard, $max, $shuffle) {
            $rows = $discard === [] ? collect() : $this->inZone($player, FateZoneEnum::Hand, $discard);
            foreach ($rows as $row) {
                $this->move($row, FateZoneEnum::Discard, $this->bottom($player, FateZoneEnum::Discard));
            }
            $limit = min($this->handMax($player->game), $max ?? PHP_INT_MAX);
            if ($shuffle) {
                $this->reshuffle($player);
            }
            $room = $limit - $this->zone($player, FateZoneEnum::Hand)->count();
            $drawn = $room > 0 ? $this->drawInto($player, $room) : [];
            $this->log($player, 'fate.refill', ['discarded' => $rows->pluck('card')->all(), 'drawn' => count($drawn), 'shuffled' => $shuffle]);

            return $drawn;
        });
    }

    /**
     * Move chosen cards from the hand to the discard pile, face up for everyone (several cards together are shown to all).
     *
     * @param  list<int>  $cards
     */
    public function discardFromHand(GamePlayer $player, array $cards): void
    {
        $this->locked($player, function () use ($player, $cards) {
            $rows = $this->inZone($player, FateZoneEnum::Hand, $cards);
            foreach ($rows as $row) {
                $this->move($row, FateZoneEnum::Discard, $this->bottom($player, FateZoneEnum::Discard));
            }
            $this->log($player, 'fate.discard', ['cards' => $rows->pluck('card')->all()]);
        });
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Flipping
    // ---------------------------------------------------------------------------------------------------------------------------

    /**
     * Flip (CRG p.8): reveal the top card to the conflict. Each + or - reveals one more, four in all at most; the + and - of one flip
     * cancel, so a flip is one kind. The result is then picked with choose().
     */
    public function flip(GamePlayer $player, int $modifier, ?string $label = null, ?string $subject = null, ?string $duelRole = null, ?int $duelWith = null): GameFateFlip
    {
        $modifier = max(-(FateCards::FLIP_MAX - 1), min(FateCards::FLIP_MAX - 1, $modifier));

        if ($duelRole !== null && ! in_array($duelRole, ['attacker', 'resister'], true)) {
            throw new FateException('A duel flip is either the attacker or the resister.');
        }

        return $this->locked($player, function () use ($player, $modifier, $label, $subject, $duelRole, $duelWith) {
            // a resisting flip links to the other player's open attacking flip (CRG p.10: an opposed duel)
            $against = null;
            if ($duelRole === 'resister') {
                $against = $duelWith !== null
                    ? GameFateFlip::query()->where('game_id', $player->game_id)->whereNull('resolved_at')->find($duelWith)
                    : null;
                if ($against === null || $against->duel_role !== 'attacker' || $against->duel_with !== null || $against->game_player_id === $player->id) {
                    throw new FateException('A resisting flip needs the other player\'s open attacking flip to resist.');
                }
            }
            $flip = GameFateFlip::create([
                'duel_role' => $duelRole,
                'duel_with' => $against?->id,
                'game_id' => $player->game_id,
                'game_player_id' => $player->id,
                'modifier' => $modifier,
                'subject' => $subject !== null && trim($subject) !== '' ? mb_substr(trim($subject), 0, 40) : null,
                'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 60) : null,
            ]);

            $revealed = [];
            for ($i = 0; $i < min(FateCards::FLIP_MAX, 1 + abs($modifier)); $i++) {
                $top = $this->takeTop($player);
                if ($top === null) {
                    break;
                }
                $top->update(['zone' => FateZoneEnum::Conflict, 'flip_id' => $flip->id, 'joker_suit' => null, 'position' => $this->bottom($player, FateZoneEnum::Conflict)]);
                $revealed[] = $top->card;
            }
            if ($revealed === []) {
                throw new FateException('There are no cards left to flip.');
            }
            if ($against !== null) {
                $against->update(['duel_with' => $flip->id]);
            }
            $this->log($player, 'fate.flip', ['flip' => $flip->id, 'modifier' => $modifier, 'revealed' => $revealed] + ($duelRole !== null ? ['duel' => $duelRole] : []));

            return $flip;
        });
    }

    /**
     * Pick the flip's result from the revealed cards (CRG p.8): with + any one, with - the lowest (a tie is the flipper's pick); a revealed
     * Red Joker may be taken even with -, and a revealed Black Joker must be taken even with +. A Red Joker takes the suit its owner names.
     */
    public function choose(GamePlayer $player, GameFateFlip $flip, int $card, ?string $suit = null): GameFateFlip
    {
        return $this->locked($player, function () use ($player, $flip, $card, $suit) {
            $flip = $this->openFlip($flip);
            $this->ownFlip($player, $flip);
            if ($flip->chosen_id !== null) {
                throw new FateException('This flip already has its result.');
            }
            $revealed = $this->revealed($flip);
            if (! in_array($card, self::legalChoices($flip->modifier, $revealed->pluck('card')->all()), true)) {
                throw new FateException('That card cannot be taken as the result of this flip.');
            }
            $row = $revealed->firstWhere('card', $card);
            $this->setJokerSuit($row, $suit);
            $flip->update(['chosen_id' => $row->id]);
            $this->log($player, 'fate.choose', ['flip' => $flip->id, 'card' => $card, 'suit' => $row->joker_suit]);

            return $flip->refresh();
        });
    }

    /**
     * The opposed duel this flip is part of, as a table sees it: both sides, their totals once said, and whose turn it is to cheat (CRG p.9-10):
     * the lowest total cheats first, the resister on a tie, then the other player. Null for a flip that is not in a duel.
     *
     * @param  Collection<int, GameFateFlip>  $byId  the open flips
     * @param  Collection<int, int>  $slotOf  game_player id => slot
     * @return array{role: string, linked: bool, total: int|null, opponent_total: int|null, cheat_turn: int|null}|null
     */
    private function duelState(GameFateFlip $f, Collection $byId, Collection $slotOf): ?array
    {
        if ($f->duel_role === null) {
            return null;
        }
        $other = $f->duel_with !== null ? $byId->get($f->duel_with) : null;
        $turn = null;
        if ($other !== null && $f->total !== null && $other->total !== null && $f->chosen_id !== null && $other->chosen_id !== null) {
            $turn = $this->cheatTurn($f, $other, $slotOf);
        }

        return [
            'role' => $f->duel_role,
            'linked' => $other !== null,
            'total' => $f->total,
            'opponent_total' => $other?->total,
            'cheat_turn' => $turn,
        ];
    }

    /**
     * Whose turn it is to cheat in a duel whose two flips have results and totals: the lower total first (the resister on a tie), then the other.
     *
     * @param  Collection<int, int>  $slotOf
     */
    private function cheatTurn(GameFateFlip $a, GameFateFlip $b, Collection $slotOf): ?int
    {
        $resister = $a->duel_role === 'resister' ? $a : $b;
        $attacker = $a->duel_role === 'resister' ? $b : $a;
        $first = $attacker->total < $resister->total ? $attacker : $resister; // equal: the resister
        $second = $first->is($attacker) ? $resister : $attacker;
        // a player's chance is used when they cheated or passed on either flip of the duel
        $done = array_unique([...($a->cheaters ?? []), ...($b->cheaters ?? [])]);
        foreach ([$first, $second] as $flip) {
            if (! in_array($flip->game_player_id, $done, true)) {
                return $slotOf[$flip->game_player_id] ?? null;
            }
        }

        return null;
    }

    /** In a linked duel nobody cheats or passes out of turn: both totals first, then the lowest total (the resister on a tie), then the other. */
    private function assertCheatTurn(GameFateFlip $flip, GamePlayer $actor): void
    {
        if ($flip->duel_role === null || $flip->duel_with === null) {
            return;
        }
        $other = GameFateFlip::query()->whereNull('resolved_at')->find($flip->duel_with);
        if ($other === null) {
            return;
        }
        if ($flip->total === null || $other->total === null || $flip->chosen_id === null || $other->chosen_id === null) {
            throw new FateException('In a duel, both players choose their result and enter their total before anyone cheats.');
        }
        $slotOf = GamePlayer::query()->where('game_id', $flip->game_id)->pluck('slot', 'id');
        $turn = $this->cheatTurn($flip, $other, $slotOf);
        if ($turn === null) {
            throw new FateException('Both players have had their chance to cheat this duel.');
        }
        if (($slotOf[$actor->id] ?? null) !== $turn) {
            throw new FateException('It is not your turn to cheat: the lowest total goes first (the resister on a tie).');
        }
    }

    /** Say what a duel flip's total is (the flip plus the stat): only its player, while the flip is open. */
    public function setTotal(GamePlayer $player, GameFateFlip $flip, int $total): GameFateFlip
    {
        return $this->locked($player, function () use ($player, $flip, $total) {
            $flip = $this->openFlip($flip);
            $this->ownFlip($player, $flip);
            if ($flip->duel_role === null) {
                throw new FateException('Only a duel flip has a total.');
            }
            $flip->update(['total' => $total]);
            $this->log($player, 'fate.total', ['flip' => $flip->id, 'total' => $total]);

            return $flip->refresh();
        });
    }

    /**
     * Cheat Fate (CRG p.9): replace the flip's result with a card from the cheater's own hand; the old result is discarded (it counts as
     * neither a flip nor a discard). Each player gets one chance per flip. A flipped Black Joker cannot be cheated, and a Red Joker
     * stops the flipper cheating their own flip. The cheater may be either player (an opposed duel), or a controlled model's controller (p.43).
     */
    public function cheat(GamePlayer $cheater, GameFateFlip $flip, int $card, ?string $suit = null): GameFateFlip
    {
        return $this->locked($cheater, function () use ($cheater, $flip, $card, $suit) {
            $flip = $this->openFlip($flip);
            if ($cheater->game_id !== $flip->game_id) {
                throw new FateException('That flip belongs to another game.');
            }
            $this->assertCheatTurn($flip, $cheater);
            $old = $flip->chosen_id ? GameFateCard::query()->find($flip->chosen_id) : null;
            if ($old === null) {
                throw new FateException('Choose the flip\'s result before cheating it.');
            }
            if ($old->card === FateCards::BLACK_JOKER) {
                throw new FateException('A flipped Black Joker cannot be cheated.');
            }
            if ($old->card === FateCards::RED_JOKER && $flip->game_player_id === $cheater->id) {
                throw new FateException('A Red Joker stops you cheating your own flip until it is discarded.');
            }
            if (in_array($cheater->id, $flip->cheaters ?? [], true)) {
                throw new FateException('You have already had your chance to cheat this flip.');
            }
            $row = $this->inZone($cheater, FateZoneEnum::Hand, [$card])->first();
            if ($row === null) {
                throw new FateException('That card is not in your hand.');
            }

            $this->move($old, FateZoneEnum::Discard, $this->bottom($old->player, FateZoneEnum::Discard));
            $row->update(['zone' => FateZoneEnum::Conflict, 'flip_id' => $flip->id, 'joker_suit' => null, 'position' => $this->bottom($cheater, FateZoneEnum::Conflict)]);
            $this->setJokerSuit($row, $suit);
            $flip->update(['chosen_id' => $row->id, 'cheaters' => [...($flip->cheaters ?? []), $cheater->id]]);
            $this->log($cheater, 'fate.cheat', ['flip' => $flip->id, 'replaced' => $old->card, 'with' => $card, 'suit' => $row->joker_suit]);

            return $flip->refresh();
        });
    }

    /** Pass on a chance to cheat: the flip is marked so nobody waits on that player (CRG p.9). */
    public function declineCheat(GamePlayer $player, GameFateFlip $flip): GameFateFlip
    {
        return $this->locked($player, function () use ($player, $flip) {
            $flip = $this->openFlip($flip);
            $this->assertCheatTurn($flip, $player);
            if (! in_array($player->id, $flip->cheaters ?? [], true)) {
                $flip->update(['cheaters' => [...($flip->cheaters ?? []), $player->id]]);
                $this->log($player, 'fate.cheat_declined', ['flip' => $flip->id]);
            }

            return $flip->refresh();
        });
    }

    /**
     * The effect has resolved: every card still in the conflict for this flip goes to its owner's discard pile, face up (CRG p.9).
     */
    public function resolve(GamePlayer $player, GameFateFlip $flip): void
    {
        $this->locked($player, function () use ($player, $flip) {
            $flip = $this->openFlip($flip);
            $this->ownFlip($player, $flip);
            $rows = $this->revealed($flip);
            foreach ($rows as $row) {
                $this->move($row, FateZoneEnum::Discard, $this->bottom($row->player, FateZoneEnum::Discard));
            }
            $flip->update(['resolved_at' => now()]);
            $this->log($player, 'fate.resolve', ['flip' => $flip->id, 'cards' => $rows->pluck('card')->all()]);
        });
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Shuffling and removing
    // ---------------------------------------------------------------------------------------------------------------------------

    /** Shuffle the discard pile back into the deck (CRG p.7, and the end phase p.32). Cards removed from the game stay out. */
    public function shuffleDiscard(GamePlayer $player): void
    {
        $this->locked($player, function () use ($player) {
            $this->reshuffle($player);
            $this->log($player, 'fate.shuffle', []);
        });
    }

    /** End phase step G (CRG p.32): each discard pile is shuffled into its deck. Hands are set aside, so they are untouched. */
    public function endPhase(Game $game): void
    {
        DB::transaction(function () use ($game) {
            Game::query()->lockForUpdate()->findOrFail($game->id);
            $this->ensureDecks($game);
            foreach ($game->players()->get() as $player) {
                $this->reshuffle($player);
            }
            $this->log(null, 'fate.end_phase', [], $game);
        });
    }

    /**
     * Set cards aside, face up and never reshuffled (CRG p.7), from the discard pile or the hand.
     *
     * @param  list<int>  $cards
     */
    public function remove(GamePlayer $player, array $cards): void
    {
        $this->locked($player, function () use ($player, $cards) {
            $rows = $this->inZone($player, [FateZoneEnum::Discard, FateZoneEnum::Hand], $cards);
            foreach ($rows as $row) {
                $this->move($row, FateZoneEnum::Removed, $this->bottom($player, FateZoneEnum::Removed));
            }
            $this->log($player, 'fate.remove', ['cards' => $rows->pluck('card')->all()]);
        });
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Hand, deck and discard manipulation
    // ---------------------------------------------------------------------------------------------------------------------------

    /**
     * Look at the top N cards of the deck (an Insight token is N=1). Returned to the owner alone, in order; the log says only that N were looked at.
     *
     * @return list<int>
     */
    public function peek(GamePlayer $player, int $n): array
    {
        $n = $this->count($n);

        return $this->locked($player, function () use ($player, $n) {
            $top = $this->zone($player, FateZoneEnum::Deck)->limit($n)->pluck('card')->all();
            $this->log($player, 'fate.peek', ['count' => count($top)]);

            return $top;
        });
    }

    /**
     * Decide what happens to the cards that were peeked at: each goes on the top or the bottom of the deck (in the order given), to the
     * discard pile (face up) or to the hand. The decisions must name exactly the deck's current top cards; that is the only way the order
     * of a deck can be changed, and it is never told to anyone.
     *
     * @param  list<array{card: int, to: string}>  $decisions
     */
    public function arrange(GamePlayer $player, array $decisions): void
    {
        $this->locked($player, function () use ($player, $decisions) {
            $n = count($decisions);
            if ($n < 1 || $n > self::MAX_AT_ONCE) {
                throw new FateException('Say what happens to between 1 and '.self::MAX_AT_ONCE.' cards.');
            }
            $top = $this->zone($player, FateZoneEnum::Deck)->limit($n)->get();
            $named = collect($decisions)->pluck('card')->map(fn ($c) => (int) $c);
            if ($top->count() !== $n || $named->unique()->count() !== $n || $named->diff($top->pluck('card'))->isNotEmpty()) {
                throw new FateException('Those are not the top cards of the deck.');
            }
            $byCard = $top->keyBy('card');

            $onTop = $onBottom = [];
            $discarded = $toHand = 0;
            foreach ($decisions as $d) {
                $row = $byCard[(int) $d['card']];
                switch ($d['to']) {
                    case 'top':
                        $onTop[] = $row;
                        break;
                    case 'bottom':
                        $onBottom[] = $row;
                        break;
                    case 'discard':
                        $this->move($row, FateZoneEnum::Discard, $this->bottom($player, FateZoneEnum::Discard));
                        $discarded++;
                        break;
                    case 'hand':
                        $this->move($row, FateZoneEnum::Hand, $this->bottom($player, FateZoneEnum::Hand));
                        $toHand++;
                        break;
                    default:
                        throw new FateException('A card can go to the top, the bottom, the discard pile or the hand.');
                }
            }
            $rest = $this->zone($player, FateZoneEnum::Deck)->whereNotIn('id', collect([...$onTop, ...$onBottom])->pluck('id'))->get();
            $this->renumber([...$onTop, ...$rest->all(), ...$onBottom]);

            $this->log($player, 'fate.arrange', [
                'looked_at' => $n,
                // only what reached the face-up discard pile is named; the rest of the order stays hidden
                'discarded' => collect($decisions)->where('to', 'discard')->pluck('card')->map(fn ($c) => (int) $c)->values()->all(),
                'to_hand' => $toHand,
                'top' => count($onTop),
                'bottom' => count($onBottom),
            ]);
        });
    }

    /** Put a card from the hand or the discard pile on the top or the bottom of the deck. From the pile it is public (the pile is face up). */
    public function putOnDeck(GamePlayer $player, int $card, string $where): void
    {
        if (! in_array($where, ['top', 'bottom'], true)) {
            throw new FateException('A card goes on the top or the bottom of the deck.');
        }
        $this->locked($player, function () use ($player, $card, $where) {
            $row = $this->inZone($player, [FateZoneEnum::Hand, FateZoneEnum::Discard], [$card])->first();
            if ($row === null) {
                throw new FateException('That card is not in your hand or discard pile.');
            }
            $fromPile = $row->zone === FateZoneEnum::Discard;
            $this->move($row, FateZoneEnum::Deck, $where === 'top' ? $this->top($player, FateZoneEnum::Deck) : $this->bottom($player, FateZoneEnum::Deck));
            $this->log($player, 'fate.put_on_deck', ['where' => $where] + ($fromPile ? ['card' => $card] : ['from_hand' => true]));
        });
    }

    /** Take a named card from the discard pile into the hand. The pile is face up, so everyone sees which card left. */
    public function drawFromDiscard(GamePlayer $player, int $card): void
    {
        $this->locked($player, function () use ($player, $card) {
            $row = $this->inZone($player, FateZoneEnum::Discard, [$card])->first();
            if ($row === null) {
                throw new FateException('That card is not in your discard pile.');
            }
            $this->move($row, FateZoneEnum::Hand, $this->bottom($player, FateZoneEnum::Hand));
            $this->log($player, 'fate.draw_from_discard', ['card' => $card]);
        });
    }

    /**
     * Everything left in the deck, in card order and never deck order, so looking through it does not reveal the shuffle. It is meant for
     * a search that ends in searchTake(), which shuffles.
     *
     * @return list<int>
     */
    public function searchView(GamePlayer $player): array
    {
        return $this->locked($player, function () use ($player) {
            $cards = $this->zone($player, FateZoneEnum::Deck)->pluck('card')->sort()->values()->all();
            $this->log($player, 'fate.search', ['looked_through' => count($cards)]);

            return $cards;
        });
    }

    /** Take a card out of the deck into the hand, then shuffle the deck. The log says a search happened, never what was found. */
    public function searchTake(GamePlayer $player, int $card): void
    {
        $this->locked($player, function () use ($player, $card) {
            $row = $this->inZone($player, FateZoneEnum::Deck, [$card])->first();
            if ($row === null) {
                throw new FateException('That card is not in your deck.');
            }
            $this->move($row, FateZoneEnum::Hand, $this->bottom($player, FateZoneEnum::Hand));
            $this->shuffleZone($player, FateZoneEnum::Deck);
            $this->log($player, 'fate.search_take', []);
        });
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Effects on the other player's hand
    // ---------------------------------------------------------------------------------------------------------------------------

    /** The most requests one player may have waiting at once, so a table cannot be flooded. */
    private const MAX_PENDING_PROMPTS = 4;

    /**
     * Ask the other player to discard N cards (they choose which) or to show their hand. The server cannot make someone choose, so it
     * puts the request in front of them and waits: they answer with discard() and respond(), or decline. Nothing about their hand
     * is told to the asker until they agree to show it.
     */
    public function demand(GamePlayer $from, string $kind, ?int $count = null, ?string $note = null): GameFatePrompt
    {
        if (! in_array($kind, [GameFatePrompt::DISCARD, GameFatePrompt::REVEAL], true)) {
            throw new FateException('Ask the other player to discard cards or to show their hand.');
        }
        if ($kind === GameFatePrompt::DISCARD && ($count === null || $count < 1 || $count > FateCards::HAND_MAX)) {
            throw new FateException('Ask for between 1 and '.FateCards::HAND_MAX.' cards.');
        }

        return $this->locked($from, function () use ($from, $kind, $count, $note) {
            $to = $this->opponentOf($from);
            if (GameFatePrompt::query()->where('game_id', $from->game_id)->where('from_player_id', $from->id)->where('status', 'pending')->count() >= self::MAX_PENDING_PROMPTS) {
                throw new FateException('Wait for the other player to answer what you have already asked.');
            }
            $prompt = GameFatePrompt::create([
                'game_id' => $from->game_id,
                'from_player_id' => $from->id,
                'to_player_id' => $to->id,
                'kind' => $kind,
                'count' => $kind === GameFatePrompt::DISCARD ? $count : null,
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 80) : null,
            ]);
            $this->log($from, 'fate.demand', ['prompt' => $prompt->id, 'to' => $to->id, 'kind' => $kind, 'count' => $prompt->count, 'note' => $prompt->note]);

            return $prompt;
        });
    }

    /**
     * Answer a request addressed to you. A discard request is marked done (you did the discarding yourself, face up). A show-your-hand
     * request either stores a snapshot of your hand for the asker, or is declined. Only the player it was addressed to may answer.
     */
    public function respond(GamePlayer $responder, int $promptId, bool $show = false): GameFatePrompt
    {
        return $this->locked($responder, function () use ($responder, $promptId, $show) {
            $prompt = GameFatePrompt::query()->where('game_id', $responder->game_id)->where('id', $promptId)->first();
            if ($prompt === null || $prompt->to_player_id !== $responder->id || $prompt->status !== 'pending') {
                throw new FateException('There is nothing for you to answer there.');
            }
            if ($prompt->kind === GameFatePrompt::REVEAL && $show) {
                $prompt->update(['status' => 'done', 'cards' => $this->zone($responder, FateZoneEnum::Hand)->pluck('card')->all()]);
                // the log says a hand was shown, never which cards
                $this->log($responder, 'fate.reveal', ['prompt' => $prompt->id, 'to' => $prompt->from_player_id]);
            } elseif ($prompt->kind === GameFatePrompt::REVEAL) {
                $prompt->update(['status' => 'declined']);
                $this->log($responder, 'fate.reveal_declined', ['prompt' => $prompt->id]);
            } else {
                $prompt->update(['status' => 'done']);
                $this->log($responder, 'fate.discard_done', ['prompt' => $prompt->id, 'count' => $prompt->count]);
            }

            return $prompt->refresh();
        });
    }

    /** The asker puts an answered request away (a shown hand is not kept on the table for ever). */
    public function dismiss(GamePlayer $viewer, int $promptId): void
    {
        $this->locked($viewer, function () use ($viewer, $promptId) {
            $prompt = GameFatePrompt::query()->where('game_id', $viewer->game_id)->where('id', $promptId)->first();
            if ($prompt === null || $prompt->from_player_id !== $viewer->id) {
                throw new FateException('There is nothing for you to put away there.');
            }
            $prompt->update(['dismissed_at' => now(), 'cards' => null]);
        });
    }

    /**
     * The other player discards N cards at random from their hand (a card effect that says so): the server picks, so nobody chooses.
     * They go face up to that player's discard pile. If the hand has fewer, it all goes.
     *
     * @return list<int> the cards discarded
     */
    public function randomDiscard(GamePlayer $from, int $n): array
    {
        $n = $this->count($n);

        return $this->locked($from, function () use ($from, $n) {
            $to = $this->opponentOf($from);
            $hand = $this->shuffled($this->zone($to, FateZoneEnum::Hand)->get()->all());
            $gone = array_slice($hand, 0, $n);
            foreach ($gone as $row) {
                $this->move($row, FateZoneEnum::Discard, $this->bottom($to, FateZoneEnum::Discard));
            }
            $cards = array_map(fn (GameFateCard $r) => $r->card, $gone);
            $this->log($from, 'fate.forced_discard', ['target' => $to->id, 'cards' => $cards]);

            return $cards;
        });
    }

    /** The other player in the game. */
    private function opponentOf(GamePlayer $player): GamePlayer
    {
        $other = GamePlayer::query()->where('game_id', $player->game_id)->where('id', '!=', $player->id)->first();
        if ($other === null) {
            throw new FateException('There is no other player to ask yet.');
        }

        return $other;
    }

    /** The decks are in use in this game. */
    public function started(Game $game): bool
    {
        return GameFateCard::query()->where('game_id', $game->id)->exists();
    }

    /**
     * The end phase as the turn advances (CRG p.32): each discard pile goes back into its deck. Games that never used Fate are left alone.
     */
    public function endPhaseIfStarted(Game $game): bool
    {
        if (! $this->started($game)) {
            return false;
        }
        $this->endPhase($game);

        return true;
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Rules
    // ---------------------------------------------------------------------------------------------------------------------------

    /**
     * Which of the revealed cards may be taken as a flip's result (CRG p.8). Jokers override: a Black Joker must be taken even with +,
     * and a Red Joker may be taken even with -. Otherwise + allows any card and - only the lowest.
     *
     * @param  list<int>  $cards
     * @return list<int>
     */
    public static function legalChoices(int $modifier, array $cards): array
    {
        if (in_array(FateCards::BLACK_JOKER, $cards, true)) {
            return [FateCards::BLACK_JOKER];
        }
        if ($modifier >= 0 || count($cards) < 2) {
            return array_values($cards);
        }
        $lowest = min(array_map(fn (int $c) => FateCards::value($c), $cards));

        return array_values(array_filter($cards, fn (int $c) => FateCards::value($c) === $lowest || $c === FateCards::RED_JOKER));
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------------------------------------------------------------

    /** Create both players' decks the first time Fate is used in a game: 54 cards each, shuffled by the server. */
    public function ensureDecks(Game $game): void
    {
        foreach ($game->players()->get(['id', 'game_id']) as $player) {
            if (GameFateCard::query()->where('game_player_id', $player->id)->exists()) {
                continue;
            }
            $order = $this->shuffled(FateCards::all());
            GameFateCard::query()->insert(array_map(fn (int $card, int $position) => [
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'card' => $card,
                'zone' => FateZoneEnum::Deck->value,
                'position' => $position,
            ], $order, array_keys($order)));
        }
    }

    /**
     * Run an action under a lock on the game, with the decks present. The lock makes two players' actions happen one at a time, and the
     * action re-checks the state it finds (a card is where it was said to be), so a stale request fails cleanly.
     */
    private function locked(GamePlayer $player, callable $fn): mixed
    {
        return DB::transaction(function () use ($player, $fn) {
            $game = Game::query()->lockForUpdate()->findOrFail($player->game_id);
            $this->ensureDecks($game);

            return $fn();
        });
    }

    /**
     * The items in a random order (Fisher-Yates over random_int).
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function shuffled(array $items): array
    {
        $items = array_values($items);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<GameFateCard> */
    private function zone(GamePlayer $player, FateZoneEnum $zone)
    {
        return GameFateCard::query()->where('game_player_id', $player->id)->where('zone', $zone->value)->orderBy('position');
    }

    /**
     * The named cards, which must all be in the given zone(s) of this player: otherwise the request is out of date or forged.
     *
     * @param  FateZoneEnum|list<FateZoneEnum>  $zones
     * @param  list<int>  $cards
     * @return Collection<int, GameFateCard>
     */
    private function inZone(GamePlayer $player, FateZoneEnum|array $zones, array $cards): Collection
    {
        $zones = array_map(fn (FateZoneEnum $z) => $z->value, is_array($zones) ? $zones : [$zones]);
        $cards = array_values(array_unique(array_map('intval', $cards)));
        if ($cards === []) {
            throw new FateException('Name at least one card.');
        }
        $rows = GameFateCard::query()->where('game_player_id', $player->id)->whereIn('zone', $zones)->whereIn('card', $cards)->orderBy('position')->get();
        if ($rows->count() !== count($cards)) {
            throw new FateException('A card is not where it was said to be.');
        }

        return $rows;
    }

    private function move(GameFateCard $row, FateZoneEnum $zone, int $position): void
    {
        $row->update(['zone' => $zone, 'position' => $position, 'joker_suit' => null, 'flip_id' => null]);
    }

    private function top(GamePlayer $player, FateZoneEnum $zone): int
    {
        $min = GameFateCard::query()->where('game_player_id', $player->id)->where('zone', $zone->value)->min('position');

        return $min === null ? 0 : (int) $min - 1;
    }

    private function bottom(GamePlayer $player, FateZoneEnum $zone): int
    {
        $max = GameFateCard::query()->where('game_player_id', $player->id)->where('zone', $zone->value)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /** @param  list<GameFateCard>  $ordered */
    private function renumber(array $ordered): void
    {
        foreach (array_values($ordered) as $i => $row) {
            if ($row->position !== $i) {
                $row->update(['position' => $i]);
            }
        }
    }

    /** The top card of the deck. When the deck is empty the discard pile is shuffled in first (CRG p.7); with nothing in either, null. */
    private function takeTop(GamePlayer $player): ?GameFateCard
    {
        $top = $this->zone($player, FateZoneEnum::Deck)->first();
        if ($top === null && $this->zone($player, FateZoneEnum::Discard)->exists()) {
            $this->reshuffle($player);
            $top = $this->zone($player, FateZoneEnum::Deck)->first();
        }

        return $top;
    }

    /** @return list<int> */
    private function drawInto(GamePlayer $player, int $n): array
    {
        $drawn = [];
        for ($i = 0; $i < $n; $i++) {
            $top = $this->takeTop($player);
            if ($top === null) {
                break;
            }
            $this->move($top, FateZoneEnum::Hand, $this->bottom($player, FateZoneEnum::Hand));
            $drawn[] = $top->card;
        }

        return $drawn;
    }

    /** The deck and the discard pile become one shuffled deck. Hands, the conflict and removed cards are left alone. */
    private function reshuffle(GamePlayer $player): void
    {
        $rows = GameFateCard::query()->where('game_player_id', $player->id)->whereIn('zone', [FateZoneEnum::Deck->value, FateZoneEnum::Discard->value])->get();
        foreach ($this->shuffled($rows->all()) as $i => $row) {
            $row->update(['zone' => FateZoneEnum::Deck, 'position' => $i, 'joker_suit' => null, 'flip_id' => null]);
        }
    }

    private function shuffleZone(GamePlayer $player, FateZoneEnum $zone): void
    {
        foreach ($this->shuffled($this->zone($player, $zone)->get()->all()) as $i => $row) {
            $row->update(['position' => $i]);
        }
    }

    private function openFlip(GameFateFlip $flip): GameFateFlip
    {
        $fresh = GameFateFlip::query()->find($flip->id);
        if ($fresh === null || $fresh->resolved_at !== null) {
            throw new FateException('That flip has already been resolved.');
        }

        return $fresh;
    }

    private function ownFlip(GamePlayer $player, GameFateFlip $flip): void
    {
        if ($flip->game_player_id !== $player->id) {
            throw new FateException('Only the player who flipped can do that.');
        }
    }

    /** @return Collection<int, GameFateCard> */
    private function revealed(GameFateFlip $flip): Collection
    {
        return GameFateCard::query()->where('flip_id', $flip->id)->where('zone', FateZoneEnum::Conflict->value)->orderBy('position')->with('player')->get();
    }

    /** A Red Joker takes the suit its owner names (CRG p.6); any other card has its own. */
    private function setJokerSuit(GameFateCard $row, ?string $suit): void
    {
        if ($row->card !== FateCards::RED_JOKER) {
            return;
        }
        if (! in_array($suit, FateCards::SUITS, true)) {
            throw new FateException('Name the suit the Red Joker takes: '.implode(', ', FateCards::SUITS).'.');
        }
        $row->update(['joker_suit' => $suit]);
    }

    private function count(int $n): int
    {
        if ($n < 1 || $n > self::MAX_AT_ONCE) {
            throw new FateException('Choose between 1 and '.self::MAX_AT_ONCE.' cards.');
        }

        return $n;
    }

    /**
     * The action log, the source of a replay and the answer when two players disagree. Public cards are named; a hidden card never is.
     *
     * @param  array<string, mixed>  $payload
     */
    private function log(?GamePlayer $player, string $action, array $payload, ?Game $game = null): void
    {
        GameLog::query()->create([
            'game_id' => $player !== null ? $player->game_id : $game?->id,
            'game_player_id' => $player?->id,
            'action' => $action,
            'payload' => $payload ?: null,
            'created_at' => now(),
        ]);
    }
}
