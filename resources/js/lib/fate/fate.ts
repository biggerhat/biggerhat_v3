/**
 * The Fate deck: the card numbering and flip rules, and a local deck for the sandbox.
 *
 * No imports, so the Node tests can run it directly (`npm run test:tabletop`).
 *
 * A game keeps its decks on the server (App\Services\Fate\FateDeckService); this file mirrors that service's rules for the practice deck
 * in the sandbox, which has no game and nobody to hide a hand from. `createLocalFate().act(name, body)` answers the same way the
 * server's endpoints do ({ state, ...private }), so one panel serves both. Keep the two in step: the same cases are tested in
 * tests/Feature/Games/GameFateTest.php and fate.test.mjs.
 *
 * CRG p.6-10, 31-32: 54 cards per player (four suits of 13, Red Joker 14, Black Joker 0), a hand of at most 7, a flip reveals up to four.
 */

export const COUNT = 54;
export const RED_JOKER = 52;
export const BLACK_JOKER = 53;
export const HAND_MAX = 7;
export const FLIP_MAX = 4;
export const MAX_AT_ONCE = 20;
export const SUITS = ['rams', 'masks', 'tomes', 'crows'] as const;
export type Suit = (typeof SUITS)[number];

export const allCards = (): number[] => Array.from({ length: COUNT }, (_, i) => i);
export const isJoker = (card: number) => card >= RED_JOKER;
export const cardValue = (card: number): number => (card === RED_JOKER ? 14 : card === BLACK_JOKER ? 0 : (card % 13) + 1);
/** The suit a card has; a joker has none (a Red Joker takes the suit its owner names). */
export const cardSuit = (card: number): Suit | null => (card < RED_JOKER ? SUITS[Math.floor(card / 13)] : null);
export const cardLabel = (card: number): string =>
    card === RED_JOKER
        ? 'Red Joker'
        : card === BLACK_JOKER
          ? 'Black Joker'
          : `${cardValue(card)} of ${cardSuit(card)![0].toUpperCase()}${cardSuit(card)!.slice(1)}`;
/** What a card shows in a corner: its value (A for the 1, J, Q, K do not exist in Fate, so the number to 13). */
export const cardFace = (card: number): string => (card === RED_JOKER ? '★' : card === BLACK_JOKER ? '★' : String(cardValue(card)));

/**
 * Which revealed cards may be the result of a flip (CRG p.8): a Black Joker must be taken even with +, a Red Joker may be taken even
 * with -, otherwise + allows any card and - only the lowest (ties are the flipper's pick).
 */
export function legalChoices(modifier: number, cards: number[]): number[] {
    if (cards.includes(BLACK_JOKER)) return [BLACK_JOKER];
    if (modifier >= 0 || cards.length < 2) return [...cards];
    const lowest = Math.min(...cards.map(cardValue));
    return cards.filter((c) => cardValue(c) === lowest || c === RED_JOKER);
}

// -------------------------------------------------------------------------------------------------------------------------------
// The state a panel shows (the server's `state`, or the local deck's)
// -------------------------------------------------------------------------------------------------------------------------------

export interface FatePlayer {
    slot: number;
    player_id?: number;
    deck_count: number;
    hand_count: number;
    over_max: number;
    /** Oldest first: the newest card is last. */
    discard: number[];
    removed: number[];
    /** Present only for a player the viewer controls. */
    hand?: number[];
}

export interface FateConflictCard {
    flip_id: number | null;
    owner_slot: number | null;
    card: number;
    joker_suit: string | null;
}

export interface FateFlip {
    id: number;
    owner_slot: number | null;
    modifier: number;
    label: string | null;
    chosen: { owner_slot: number | null; card: number; joker_suit: string | null } | null;
    legal: number[];
    cheaters: number[];
}

export interface FateState {
    started: boolean;
    hand_max: number;
    players: FatePlayer[];
    conflict: FateConflictCard[];
    flips: FateFlip[];
}

export type FateResult = { error?: string; state?: FateState; drawn?: number[]; peeked?: number[]; deck?: number[]; flip?: number };

/** What the panel calls: the server's endpoints through fetch, or the local deck. */
export interface FateFeed {
    state: { value: FateState | null };
    busy: { value: boolean };
    error: { value: string | null };
    act: (name: string, body?: Record<string, unknown>) => Promise<FateResult>;
}

// -------------------------------------------------------------------------------------------------------------------------------
// The local deck (sandbox): the same rules as FateDeckService, in the browser
// -------------------------------------------------------------------------------------------------------------------------------

type Zone = 'deck' | 'hand' | 'conflict' | 'discard' | 'removed';
interface Row {
    slot: number;
    card: number;
    zone: Zone;
    position: number;
    jokerSuit: string | null;
    flipId: number | null;
}
interface Flip {
    id: number;
    slot: number;
    modifier: number;
    label: string | null;
    chosen: Row | null;
    cheaters: number[];
    resolved: boolean;
}

/** A shuffle that cannot be predicted (crypto), unbiased by rejection sampling. */
export function secureRandomInt(maxExclusive: number): number {
    const cryptoObj = (globalThis as { crypto?: { getRandomValues: (a: Uint32Array) => Uint32Array } }).crypto;
    if (!cryptoObj) return Math.floor(Math.random() * maxExclusive);
    const limit = Math.floor(0x100000000 / maxExclusive) * maxExclusive;
    const buf = new Uint32Array(1);
    do cryptoObj.getRandomValues(buf);
    while (buf[0] >= limit);
    return buf[0] % maxExclusive;
}

export function shuffled<T>(items: T[], randomInt: (n: number) => number = secureRandomInt): T[] {
    const a = [...items];
    for (let i = a.length - 1; i > 0; i--) {
        const j = randomInt(i + 1);
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

class FateError extends Error {}

export function createLocalFate(slots: number[] = [1, 2], randomInt: (n: number) => number = secureRandomInt) {
    let rows: Row[] = [];
    let flips: Flip[] = [];
    let nextFlip = 1;

    function ensure() {
        if (rows.length) return;
        for (const slot of slots) {
            shuffled(allCards(), randomInt).forEach((card, position) =>
                rows.push({ slot, card, zone: 'deck', position, jokerSuit: null, flipId: null }),
            );
        }
    }
    const zone = (slot: number, z: Zone) => rows.filter((r) => r.slot === slot && r.zone === z).sort((a, b) => a.position - b.position);
    const bottom = (slot: number, z: Zone) => Math.max(-1, ...zone(slot, z).map((r) => r.position)) + 1;
    const top = (slot: number, z: Zone) => Math.min(1, ...zone(slot, z).map((r) => r.position)) - 1;
    const move = (r: Row, z: Zone, position: number) => Object.assign(r, { zone: z, position, jokerSuit: null, flipId: null });
    function inZone(slot: number, zones: Zone[], cards: number[]): Row[] {
        const unique = [...new Set(cards)];
        if (!unique.length) throw new FateError('Name at least one card.');
        const found = rows.filter((r) => r.slot === slot && zones.includes(r.zone) && unique.includes(r.card));
        if (found.length !== unique.length) throw new FateError('A card is not where it was said to be.');
        return found.sort((a, b) => a.position - b.position);
    }
    /** One named card that must be in one of these zones, with the server's wording when it is not. */
    function one(slot: number, zones: Zone[], card: unknown, message: string): Row {
        const row = rows.find((r) => r.slot === slot && zones.includes(r.zone) && r.card === Number(card));
        if (!row) throw new FateError(message);
        return row;
    }
    function reshuffle(slot: number) {
        const all = [...zone(slot, 'deck'), ...zone(slot, 'discard')];
        shuffled(all, randomInt).forEach((r, i) => Object.assign(r, { zone: 'deck', position: i, jokerSuit: null, flipId: null }));
    }
    function takeTop(slot: number): Row | null {
        let t = zone(slot, 'deck')[0] ?? null;
        if (!t && zone(slot, 'discard').length) {
            reshuffle(slot);
            t = zone(slot, 'deck')[0] ?? null;
        }
        return t;
    }
    function drawInto(slot: number, n: number): number[] {
        const drawn: number[] = [];
        for (let i = 0; i < n; i++) {
            const t = takeTop(slot);
            if (!t) break;
            move(t, 'hand', bottom(slot, 'hand'));
            drawn.push(t.card);
        }
        return drawn;
    }
    const count = (n: unknown) => {
        const v = Number(n);
        if (!Number.isInteger(v) || v < 1 || v > MAX_AT_ONCE) throw new FateError(`Choose between 1 and ${MAX_AT_ONCE} cards.`);
        return v;
    };
    const openFlip = (id: unknown) => {
        const f = flips.find((x) => x.id === Number(id));
        if (!f || f.resolved) throw new FateError('That flip has already been resolved.');
        return f;
    };
    const revealed = (f: Flip) => rows.filter((r) => r.flipId === f.id && r.zone === 'conflict').sort((a, b) => a.position - b.position);
    function setJokerSuit(r: Row, suit: unknown) {
        if (r.card !== RED_JOKER) return;
        if (!SUITS.includes(suit as Suit)) throw new FateError(`Name the suit the Red Joker takes: ${SUITS.join(', ')}.`);
        r.jokerSuit = suit as string;
    }

    function state(): FateState {
        const slotOf = (r: Row) => r.slot;
        return {
            started: rows.length > 0,
            hand_max: HAND_MAX,
            players: slots.map((slot) => ({
                slot,
                deck_count: zone(slot, 'deck').length,
                hand_count: zone(slot, 'hand').length,
                over_max: Math.max(0, zone(slot, 'hand').length - HAND_MAX),
                discard: zone(slot, 'discard').map((r) => r.card),
                removed: zone(slot, 'removed').map((r) => r.card),
                // the sandbox is one person at one table: every hand is theirs
                hand: zone(slot, 'hand').map((r) => r.card),
            })),
            conflict: rows
                .filter((r) => r.zone === 'conflict')
                .sort((a, b) => a.position - b.position)
                .map((r) => ({ flip_id: r.flipId, owner_slot: slotOf(r), card: r.card, joker_suit: r.jokerSuit })),
            flips: flips
                .filter((f) => !f.resolved)
                .map((f) => ({
                    id: f.id,
                    owner_slot: f.slot,
                    modifier: f.modifier,
                    label: f.label,
                    chosen: f.chosen ? { owner_slot: f.chosen.slot, card: f.chosen.card, joker_suit: f.chosen.jokerSuit } : null,
                    legal: f.chosen
                        ? []
                        : legalChoices(
                              f.modifier,
                              revealed(f).map((r) => r.card),
                          ),
                    cheaters: f.cheaters,
                })),
        };
    }

    const actions: Record<string, (slot: number, b: Record<string, unknown>) => Partial<FateResult> | void> = {
        draw: (slot, b) => ({ drawn: drawInto(slot, count(b.n)) }),
        refill: (slot, b) => {
            const discard = (b.discard as number[]) ?? [];
            for (const r of discard.length ? inZone(slot, ['hand'], discard) : []) move(r, 'discard', bottom(slot, 'discard'));
            const room = HAND_MAX - zone(slot, 'hand').length;
            return { drawn: room > 0 ? drawInto(slot, room) : [] };
        },
        discard: (slot, b) => {
            for (const r of inZone(slot, ['hand'], b.cards as number[])) move(r, 'discard', bottom(slot, 'discard'));
        },
        flip: (slot, b) => {
            const modifier = Math.max(-(FLIP_MAX - 1), Math.min(FLIP_MAX - 1, Math.trunc(Number(b.modifier) || 0)));
            const flip: Flip = {
                id: nextFlip++,
                slot,
                modifier,
                label: b.label ? String(b.label).trim().slice(0, 60) || null : null,
                chosen: null,
                cheaters: [],
                resolved: false,
            };
            let shown = 0;
            for (let i = 0; i < Math.min(FLIP_MAX, 1 + Math.abs(modifier)); i++) {
                const t = takeTop(slot);
                if (!t) break;
                Object.assign(t, { zone: 'conflict', flipId: flip.id, jokerSuit: null, position: bottom(slot, 'conflict') });
                shown++;
            }
            if (!shown) throw new FateError('There are no cards left to flip.');
            flips.push(flip);
            return { flip: flip.id };
        },
        choose: (slot, b) => {
            const f = openFlip(b.flip);
            if (f.slot !== slot) throw new FateError('Only the player who flipped can do that.');
            if (f.chosen) throw new FateError('This flip already has its result.');
            const shown = revealed(f);
            if (
                !legalChoices(
                    f.modifier,
                    shown.map((r) => r.card),
                ).includes(Number(b.card))
            )
                throw new FateError('That card cannot be taken as the result of this flip.');
            const row = shown.find((r) => r.card === Number(b.card))!;
            setJokerSuit(row, b.suit);
            f.chosen = row;
        },
        cheat: (slot, b) => {
            const f = openFlip(b.flip);
            const old = f.chosen;
            if (!old) throw new FateError("Choose the flip's result before cheating it.");
            if (old.card === BLACK_JOKER) throw new FateError('A flipped Black Joker cannot be cheated.');
            if (old.card === RED_JOKER && f.slot === slot) throw new FateError('A Red Joker stops you cheating your own flip until it is discarded.');
            if (f.cheaters.includes(slot)) throw new FateError('You have already had your chance to cheat this flip.');
            const row = one(slot, ['hand'], b.card, 'That card is not in your hand.');
            move(old, 'discard', bottom(old.slot, 'discard'));
            Object.assign(row, { zone: 'conflict', flipId: f.id, jokerSuit: null, position: bottom(slot, 'conflict') });
            setJokerSuit(row, b.suit);
            f.chosen = row;
            f.cheaters.push(slot);
        },
        'cheat.decline': (slot, b) => {
            const f = openFlip(b.flip);
            if (!f.cheaters.includes(slot)) f.cheaters.push(slot);
        },
        resolve: (slot, b) => {
            const f = openFlip(b.flip);
            if (f.slot !== slot) throw new FateError('Only the player who flipped can do that.');
            for (const r of revealed(f)) move(r, 'discard', bottom(r.slot, 'discard'));
            f.resolved = true;
        },
        shuffle: (slot) => reshuffle(slot),
        end_phase: () => {
            for (const s of slots) reshuffle(s);
        },
        remove: (slot, b) => {
            for (const r of inZone(slot, ['discard', 'hand'], b.cards as number[])) move(r, 'removed', bottom(slot, 'removed'));
        },
        peek: (slot, b) => ({
            peeked: zone(slot, 'deck')
                .slice(0, count(b.n))
                .map((r) => r.card),
        }),
        arrange: (slot, b) => {
            const decisions = (b.decisions as { card: number; to: string }[]) ?? [];
            const n = decisions.length;
            if (n < 1 || n > MAX_AT_ONCE) throw new FateError(`Say what happens to between 1 and ${MAX_AT_ONCE} cards.`);
            const topRows = zone(slot, 'deck').slice(0, n);
            const named = decisions.map((d) => Number(d.card));
            if (topRows.length !== n || new Set(named).size !== n || named.some((c) => !topRows.some((r) => r.card === c)))
                throw new FateError('Those are not the top cards of the deck.');
            const onTop: Row[] = [];
            const onBottom: Row[] = [];
            for (const d of decisions) {
                const row = topRows.find((r) => r.card === Number(d.card))!;
                if (d.to === 'top') onTop.push(row);
                else if (d.to === 'bottom') onBottom.push(row);
                else if (d.to === 'discard') move(row, 'discard', bottom(slot, 'discard'));
                else if (d.to === 'hand') move(row, 'hand', bottom(slot, 'hand'));
                else throw new FateError('A card can go to the top, the bottom, the discard pile or the hand.');
            }
            const rest = zone(slot, 'deck').filter((r) => !onTop.includes(r) && !onBottom.includes(r));
            [...onTop, ...rest, ...onBottom].forEach((r, i) => (r.position = i));
        },
        put: (slot, b) => {
            if (b.where !== 'top' && b.where !== 'bottom') throw new FateError('A card goes on the top or the bottom of the deck.');
            const row = one(slot, ['hand', 'discard'], b.card, 'That card is not in your hand or discard pile.');
            move(row, 'deck', b.where === 'top' ? top(slot, 'deck') : bottom(slot, 'deck'));
        },
        draw_from_discard: (slot, b) => {
            const row = one(slot, ['discard'], b.card, 'That card is not in your discard pile.');
            move(row, 'hand', bottom(slot, 'hand'));
        },
        search: (slot) => ({
            deck: zone(slot, 'deck')
                .map((r) => r.card)
                .sort((a, b) => a - b),
        }),
        'search.take': (slot, b) => {
            const row = one(slot, ['deck'], b.card, 'That card is not in your deck.');
            move(row, 'hand', bottom(slot, 'hand'));
            shuffled(zone(slot, 'deck'), randomInt).forEach((r, i) => (r.position = i));
        },
    };

    return {
        state: (): FateState => {
            return state();
        },
        reset() {
            rows = [];
            flips = [];
            nextFlip = 1;
        },
        /** Run an action as a slot. Answers like the server: the new state, any private result, or an error (and nothing changes). */
        act(name: string, body: Record<string, unknown> = {}): FateResult {
            const slot = Number(body.slot) || slots[0];
            const fn = actions[name];
            if (!fn) return { error: `Unknown Fate action: ${name}` };
            // run on a copy so a refused action changes nothing (the server rolls its transaction back)
            const before = JSON.stringify({ rows, flips, nextFlip });
            try {
                ensure();
                const result = fn(slot, body) ?? {};

                return { ...result, state: state() };
            } catch (e) {
                const saved = JSON.parse(before) as { rows: Row[]; flips: Flip[]; nextFlip: number };
                rows = saved.rows;
                flips = saved.flips.map((f) => ({
                    ...f,
                    chosen: f.chosen ? (rows.find((r) => r.slot === f.chosen!.slot && r.card === f.chosen!.card) ?? null) : null,
                }));
                nextFlip = saved.nextFlip;
                if (e instanceof FateError) return { error: e.message, state: state() };
                throw e;
            }
        },
    };
}
