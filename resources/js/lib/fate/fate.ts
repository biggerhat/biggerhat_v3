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
    /** What the flip is for on the board (a model's id), so its result can be drawn beside that model. */
    subject: string | null;
    chosen: { owner_slot: number | null; card: number; joker_suit: string | null } | null;
    legal: number[];
    cheaters: number[];
}

/** A request the other player has made of me: discard N cards (my choice) or show my hand. */
export interface FatePrompt {
    id: number;
    from_slot: number | null;
    to_slot: number | null;
    kind: 'discard' | 'reveal';
    count: number | null;
    note: string | null;
}
/** A request I made that is still waiting. */
export interface FateSent {
    id: number;
    to_slot: number | null;
    kind: 'discard' | 'reveal';
    count: number | null;
}
/** A hand someone chose to show me (or refused to). Only the player who asked is given the cards. */
export interface FateAnswer {
    id: number;
    owner_slot: number | null;
    declined: boolean;
    cards: number[] | null;
}

export interface FateState {
    started: boolean;
    hand_max: number;
    players: FatePlayer[];
    conflict: FateConflictCard[];
    flips: FateFlip[];
    prompts: FatePrompt[];
    sent: FateSent[];
    answers: FateAnswer[];
}

export interface FateLogLine {
    id: number;
    at: string;
    text: string;
}

export type FateResult = {
    error?: string;
    state?: FateState;
    drawn?: number[];
    peeked?: number[];
    deck?: number[];
    flip?: number;
    prompt?: number;
    discarded?: number[];
};

/** What the panel calls: the server's endpoints through fetch, or the local deck. */
export interface FateFeed {
    state: { value: FateState | null };
    busy: { value: boolean };
    error: { value: string | null };
    act: (name: string, body?: Record<string, unknown>) => Promise<FateResult>;
    /** The history as readable lines, newest first. */
    log?: () => Promise<FateLogLine[]>;
    /** Load the deck and listen for changes (a game's feed); a local deck needs nothing. */
    start?: () => Promise<void>;
}

/**
 * One history line, in the same words as App\Support\Fate\FateLog. Every line is safe for everyone: a hidden card is only ever "N cards".
 */
export function describeFate(action: string, p: Record<string, unknown>, who: string, names: Record<number, string> = {}): string {
    const labels = (list?: unknown) => (Array.isArray(list) && list.length ? list.map((c) => cardLabel(Number(c))).join(', ') : 'nothing');
    const n = (count: number, word = 'card') => `${count} ${word}${count === 1 ? '' : 's'}`;
    const mod = (m: number) => (m > 0 ? `+${m}` : m < 0 ? String(m) : 'a plain flip');
    const num = (v: unknown) => Number(v ?? 0);
    const other = names[num(p.to)] ?? 'the other player';
    const suit = p.suit ? ` as ${p.suit}` : '';
    switch (action) {
        case 'fate.draw':
            return `${who} drew ${n(num(p.count))}.`;
        case 'fate.refill':
            return `${who} discarded ${labels(p.discarded)} and drew up to the hand limit (${n(num(p.drawn))} drawn).`;
        case 'fate.discard':
            return `${who} discarded ${labels(p.cards)}.`;
        case 'fate.flip':
            return `${who} flipped ${mod(num(p.modifier))}: ${labels(p.revealed)}.`;
        case 'fate.choose':
            return `${who} took ${labels([p.card])}${suit} as the result.`;
        case 'fate.cheat':
            return `${who} cheated Fate: ${labels([p.replaced])} replaced by ${labels([p.with])}${suit}.`;
        case 'fate.cheat_declined':
            return `${who} passed on cheating.`;
        case 'fate.resolve':
            return `${who} resolved the flip: ${labels(p.cards)} discarded.`;
        case 'fate.shuffle':
            return `${who} shuffled their discard pile into their deck.`;
        case 'fate.end_phase':
            return 'End phase: each discard pile was shuffled into its deck.';
        case 'fate.remove':
            return `${who} set aside ${labels(p.cards)}, removed from the game.`;
        case 'fate.peek':
            return `${who} looked at ${n(num(p.count))} on top of their deck.`;
        case 'fate.arrange':
            return `${who} rearranged the top ${n(num(p.looked_at))} of their deck${Array.isArray(p.discarded) && p.discarded.length ? ` (discarded ${labels(p.discarded)})` : ''}.`;
        case 'fate.put_on_deck':
            return `${who} put ${p.card !== undefined ? labels([p.card]) : 'a card from their hand'} on the ${p.where ?? 'top'} of their deck.`;
        case 'fate.draw_from_discard':
            return `${who} took ${labels([p.card])} back from their discard pile.`;
        case 'fate.search':
            return `${who} looked through their deck.`;
        case 'fate.search_take':
            return `${who} took a card from their deck and shuffled it.`;
        case 'fate.demand':
            return p.kind === 'reveal'
                ? `${who} asked ${other} to show their hand${p.note ? ` (${p.note})` : ''}.`
                : `${who} asked ${other} to discard ${n(num(p.count))}${p.note ? ` (${p.note})` : ''}.`;
        case 'fate.reveal':
            return `${who} showed their hand to ${other}.`;
        case 'fate.reveal_declined':
            return `${who} declined to show their hand.`;
        case 'fate.discard_done':
            return `${who} finished discarding ${n(num(p.count))}.`;
        case 'fate.forced_discard':
            return `${names[num(p.target)] ?? 'The other player'} discarded ${labels(p.cards)} at random, because of ${who}.`;
        default:
            return `${who}: ${action}.`;
    }
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
interface Prompt {
    id: number;
    from: number;
    to: number;
    kind: 'discard' | 'reveal';
    count: number | null;
    note: string | null;
    status: 'pending' | 'done' | 'declined';
    cards: number[] | null;
    dismissed: boolean;
}
interface Flip {
    id: number;
    slot: number;
    modifier: number;
    label: string | null;
    subject: string | null;
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

export function createLocalFate(
    slots: number[] = [1, 2],
    randomInt: (n: number) => number = secureRandomInt,
    names: Record<number, string> = { 1: 'Blue', 2: 'Red' },
    handMax: number = HAND_MAX,
) {
    let rows: Row[] = [];
    let flips: Flip[] = [];
    let nextFlip = 1;
    let prompts: Prompt[] = [];
    let nextPrompt = 1;
    let history: FateLogLine[] = [];
    let nextLog = 1;
    /** What the running action wants written to the history (set by the actions that name hidden or private details). */
    let note: Record<string, unknown> = {};

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
            hand_max: handMax,
            players: slots.map((slot) => ({
                slot,
                deck_count: zone(slot, 'deck').length,
                hand_count: zone(slot, 'hand').length,
                over_max: Math.max(0, zone(slot, 'hand').length - handMax),
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
                    subject: f.subject,
                    chosen: f.chosen ? { owner_slot: f.chosen.slot, card: f.chosen.card, joker_suit: f.chosen.jokerSuit } : null,
                    legal: f.chosen
                        ? []
                        : legalChoices(
                              f.modifier,
                              revealed(f).map((r) => r.card),
                          ),
                    cheaters: f.cheaters,
                })),
            // the sandbox is one person at one table, so every request and every shown hand is theirs to see
            prompts: prompts
                .filter((q) => q.status === 'pending')
                .map((q) => ({ id: q.id, from_slot: q.from, to_slot: q.to, kind: q.kind, count: q.count, note: q.note })),
            sent: prompts.filter((q) => q.status === 'pending').map((q) => ({ id: q.id, to_slot: q.to, kind: q.kind, count: q.count })),
            answers: prompts
                .filter((q) => q.kind === 'reveal' && q.status !== 'pending' && !q.dismissed)
                .map((q) => ({ id: q.id, owner_slot: q.to, declined: q.status === 'declined', cards: q.status === 'done' ? q.cards : null })),
        };
    }
    const otherSlot = (slot: number) => {
        const other = slots.find((s) => s !== slot);
        if (other === undefined) throw new FateError('There is no other player to ask yet.');
        return other;
    };

    const actions: Record<string, (slot: number, b: Record<string, unknown>) => Partial<FateResult> | void> = {
        draw: (slot, b) => ({ drawn: drawInto(slot, count(b.n)) }),
        refill: (slot, b) => {
            const discard = (b.discard as number[]) ?? [];
            for (const r of discard.length ? inZone(slot, ['hand'], discard) : []) move(r, 'discard', bottom(slot, 'discard'));
            const room = handMax - zone(slot, 'hand').length;
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
                subject: b.subject ? String(b.subject).trim().slice(0, 40) || null : null,
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
            note = { modifier, revealed: rows.filter((r) => r.flipId === flip.id).map((r) => r.card) };
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
            note = { card: row.card, suit: row.jokerSuit };
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
            note = { replaced: old.card, with: row.card, suit: row.jokerSuit };
        },
        'cheat.decline': (slot, b) => {
            const f = openFlip(b.flip);
            if (!f.cheaters.includes(slot)) f.cheaters.push(slot);
        },
        resolve: (slot, b) => {
            const f = openFlip(b.flip);
            if (f.slot !== slot) throw new FateError('Only the player who flipped can do that.');
            const gone = revealed(f);
            note = { cards: gone.map((r) => r.card) };
            for (const r of gone) move(r, 'discard', bottom(r.slot, 'discard'));
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
            const discarded: number[] = [];
            for (const d of decisions) {
                const row = topRows.find((r) => r.card === Number(d.card))!;
                if (d.to === 'top') onTop.push(row);
                else if (d.to === 'bottom') onBottom.push(row);
                else if (d.to === 'discard') {
                    move(row, 'discard', bottom(slot, 'discard'));
                    discarded.push(row.card);
                } else if (d.to === 'hand') move(row, 'hand', bottom(slot, 'hand'));
                else throw new FateError('A card can go to the top, the bottom, the discard pile or the hand.');
            }
            const rest = zone(slot, 'deck').filter((r) => !onTop.includes(r) && !onBottom.includes(r));
            [...onTop, ...rest, ...onBottom].forEach((r, i) => (r.position = i));
            note = { looked_at: n, discarded };
        },
        put: (slot, b) => {
            if (b.where !== 'top' && b.where !== 'bottom') throw new FateError('A card goes on the top or the bottom of the deck.');
            const row = one(slot, ['hand', 'discard'], b.card, 'That card is not in your hand or discard pile.');
            note = { where: b.where, ...(row.zone === 'discard' ? { card: row.card } : { from_hand: true }) };
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
        // ----- effects on the other player's hand -----
        demand: (slot, b) => {
            const kind = b.kind === 'reveal' ? 'reveal' : b.kind === 'discard' ? 'discard' : null;
            if (!kind) throw new FateError('Ask the other player to discard cards or to show their hand.');
            const asked = Number(b.count);
            if (kind === 'discard' && !(Number.isInteger(asked) && asked >= 1 && asked <= HAND_MAX))
                throw new FateError(`Ask for between 1 and ${HAND_MAX} cards.`);
            const to = otherSlot(slot);
            if (prompts.filter((q) => q.from === slot && q.status === 'pending').length >= 4)
                throw new FateError('Wait for the other player to answer what you have already asked.');
            const q: Prompt = {
                id: nextPrompt++,
                from: slot,
                to,
                kind,
                count: kind === 'discard' ? asked : null,
                note: b.note ? String(b.note).trim().slice(0, 80) || null : null,
                status: 'pending',
                cards: null,
                dismissed: false,
            };
            prompts.push(q);
            note = { prompt: q.id, to, kind, count: q.count, note: q.note };
            return { prompt: q.id };
        },
        random_discard: (slot, b) => {
            const to = otherSlot(slot);
            const gone = shuffled(zone(to, 'hand'), randomInt).slice(0, count(b.n));
            for (const r of gone) move(r, 'discard', bottom(to, 'discard'));
            note = { target: to, cards: gone.map((r) => r.card) };
            return { discarded: gone.map((r) => r.card) };
        },
        respond: (slot, b) => {
            const q = prompts.find((x) => x.id === Number(b.prompt));
            if (!q || q.to !== slot || q.status !== 'pending') throw new FateError('There is nothing for you to answer there.');
            if (q.kind === 'reveal' && b.show) {
                q.status = 'done';
                q.cards = zone(slot, 'hand').map((r) => r.card);
                note = { action: 'fate.reveal', to: q.from };
            } else if (q.kind === 'reveal') {
                q.status = 'declined';
                note = { action: 'fate.reveal_declined' };
            } else {
                q.status = 'done';
                note = { action: 'fate.discard_done', count: q.count };
            }
        },
        dismiss: (slot, b) => {
            const q = prompts.find((x) => x.id === Number(b.prompt));
            if (!q || q.from !== slot) throw new FateError('There is nothing for you to put away there.');
            q.dismissed = true;
            q.cards = null;
            note = { action: 'none' };
        },
    };

    /** What each action writes to the history, from its request and what it set aside in `note`. */
    function record(name: string, slot: number, body: Record<string, unknown>, result: FateResult) {
        const n = note;
        const table: Record<string, [string, Record<string, unknown>]> = {
            draw: ['fate.draw', { count: result.drawn?.length ?? 0 }],
            refill: ['fate.refill', { discarded: body.discard ?? [], drawn: result.drawn?.length ?? 0 }],
            discard: ['fate.discard', { cards: body.cards }],
            flip: ['fate.flip', n],
            choose: ['fate.choose', n],
            cheat: ['fate.cheat', n],
            'cheat.decline': ['fate.cheat_declined', {}],
            resolve: ['fate.resolve', n],
            shuffle: ['fate.shuffle', {}],
            end_phase: ['fate.end_phase', {}],
            remove: ['fate.remove', { cards: body.cards }],
            peek: ['fate.peek', { count: result.peeked?.length ?? 0 }],
            arrange: ['fate.arrange', n],
            put: ['fate.put_on_deck', n],
            draw_from_discard: ['fate.draw_from_discard', { card: body.card }],
            search: ['fate.search', {}],
            'search.take': ['fate.search_take', {}],
            demand: ['fate.demand', n],
            random_discard: ['fate.forced_discard', n],
            respond: [String(n.action ?? 'none'), n],
            dismiss: ['none', {}],
        };
        const [action, payload] = table[name] ?? ['none', {}];
        if (action === 'none') return;
        history = [
            { id: nextLog++, at: new Date().toISOString(), text: describeFate(action, payload, names[slot] ?? `Player ${slot}`, names) },
            ...history,
        ].slice(0, 100);
    }

    return {
        state: (): FateState => {
            return state();
        },
        reset() {
            rows = [];
            flips = [];
            nextFlip = 1;
            prompts = [];
            nextPrompt = 1;
            history = [];
            nextLog = 1;
        },
        /** The history, newest first (a game reads the same from the server). */
        log: (): FateLogLine[] => history,
        /** Run an action as a slot. Answers like the server: the new state, any private result, or an error (and nothing changes). */
        act(name: string, body: Record<string, unknown> = {}): FateResult {
            const slot = Number(body.slot) || slots[0];
            const fn = actions[name];
            if (!fn) return { error: `Unknown Fate action: ${name}` };
            // run on a copy so a refused action changes nothing (the server rolls its transaction back)
            const before = JSON.stringify({ rows, flips, nextFlip, prompts, nextPrompt });
            note = {};
            try {
                ensure();
                const result = fn(slot, body) ?? {};
                record(name, slot, body, result);

                return { ...result, state: state() };
            } catch (e) {
                const saved = JSON.parse(before) as { rows: Row[]; flips: Flip[]; nextFlip: number; prompts: Prompt[]; nextPrompt: number };
                rows = saved.rows;
                flips = saved.flips.map((f) => ({
                    ...f,
                    chosen: f.chosen ? (rows.find((r) => r.slot === f.chosen!.slot && r.card === f.chosen!.card) ?? null) : null,
                }));
                nextFlip = saved.nextFlip;
                prompts = saved.prompts;
                nextPrompt = saved.nextPrompt;
                if (e instanceof FateError) return { error: e.message, state: state() };
                throw e;
            }
        },
    };
}
