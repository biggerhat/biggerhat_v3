<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import FatePeekDialog from '@/components/Fate/FatePeekDialog.vue';
import FatePileDialog from '@/components/Fate/FatePileDialog.vue';
import FateSearchDialog from '@/components/Fate/FateSearchDialog.vue';
import FateStartDialog from '@/components/Fate/FateStartDialog.vue';
import { Button } from '@/components/ui/button';
import {
    BLACK_JOKER,
    COUNT,
    RED_JOKER,
    SUITS,
    cardLabel,
    type FateAnswer,
    type FateFeed,
    type FateFlip,
    type FateLogLine,
    type FatePlayer,
    type FatePrompt,
} from '@/lib/fate/fate';
import { ChevronDown, Minus, Plus, Undo2 } from 'lucide-vue-next';
import { useElementSize } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

/**
 * The Fate deck laid out like a table: the other side as a thin strip, then your mat (deck, the conflict in the middle, your discard
 * pile) and your hand along the bottom. Tap the deck to draw, a hand card for what to do with it, the pile to look through it.
 *
 * It is fed by a `feed`: a game's server deck (useFateGame) or the sandbox's practice deck (useLocalFate); both answer the same way,
 * so this component does not know which it has. A hand is shown only for a side the viewer controls (the state carries no cards for any
 * other hand); everyone else's hand is a row of card backs, as many as it holds.
 */
const props = defineProps<{
    feed: FateFeed;
    /** Display names by slot. */
    names?: Record<number, string>;
    /** Someone watching: public state only, nothing to press. */
    readOnly?: boolean;
}>();

const state = computed(() => props.feed.state.value);
const players = computed(() => state.value?.players ?? []);
const busy = computed(() => props.feed.busy.value);
const controlled = computed(() => (props.readOnly ? [] : players.value.filter((p) => p.hand !== undefined)));
const acting = ref<number | null>(null);
const actingSlot = computed(() => (controlled.value.some((p) => p.slot === acting.value) ? acting.value : (controlled.value[0]?.slot ?? null)));
const mine = computed(() => players.value.find((p) => p.slot === actingSlot.value) ?? null);
/** Everyone the mat is not about: the other side, or both sides for someone watching. */
const strips = computed(() => players.value.filter((p) => p.slot !== actingSlot.value));
const nameOf = (slot: number | null) => (slot === null ? 'Someone' : (props.names?.[slot] ?? `Player ${slot}`));
const deckSize = (p: { deck_count: number }) => (state.value?.started ? p.deck_count : COUNT);
const topOf = (p: FatePlayer) => p.discard[p.discard.length - 1];

/** A hand limit lower than the game's, per player (a master or effect can shrink the hand). Not kept past a reload. */
const limits = ref<Record<number, number>>({});
const handLimit = computed(() => Math.min(state.value?.hand_max ?? 7, limits.value[actingSlot.value ?? 0] ?? state.value?.hand_max ?? 7));
const lowered = computed(() => handLimit.value < (state.value?.hand_max ?? 7));
function changeLimit(by: number) {
    if (actingSlot.value === null) return;
    limits.value = { ...limits.value, [actingSlot.value]: Math.max(1, Math.min(state.value?.hand_max ?? 7, handLimit.value + by)) };
}
/** How far over the limit this player's hand is, by their own limit. */
const overMax = computed(() => Math.max(0, (mine.value?.hand_count ?? 0) - handLimit.value));
/** What a refill sends: only a limit that is lower than the game's. */
const maxBody = () => (lowered.value ? { max: handLimit.value } : {});

const run = (name: string, body: Record<string, unknown> = {}) => props.feed.act(name, { slot: actingSlot.value, ...body });

// ----- a hand card picked for the action bar -----
const picked = ref<number | null>(null);

// The hand is a fan, not a scroller: the cards overlap just enough to fit the width, and each shows its corner (value and suit) so one
// under another can still be read. A roomy hand sits side by side.
const HAND_CARD_W = 52; // a large card, 3.25rem
const HAND_GAP = 6;
const HAND_MIN_STEP = 20; // never less of a card than its corner
const handEl = ref<HTMLElement | null>(null);
const { width: handWidth } = useElementSize(handEl);
/** How far each card sits from the one before it. */
const handStep = computed(() => {
    const n = mine.value?.hand?.length ?? 0;
    if (n < 2 || handWidth.value === 0) return HAND_CARD_W + HAND_GAP;
    return Math.max(HAND_MIN_STEP, Math.min(HAND_CARD_W + HAND_GAP, (handWidth.value - HAND_CARD_W) / (n - 1)));
});
const handOverlaps = computed(() => handStep.value < HAND_CARD_W);
function pick(card: number) {
    picked.value = picked.value === card ? null : card;
}
// forget the pick when that card has left the hand
watch(mine, (p) => {
    if (picked.value !== null && !p?.hand?.includes(picked.value)) picked.value = null;
});
watch(actingSlot, () => (picked.value = null));
async function handAct(name: string, body: Record<string, unknown>) {
    const r = await run(name, body);
    if (!r.error) picked.value = null;
}

// ----- flipping -----
const modifier = ref(0);
const label = ref('');
const labelOpen = ref(false);
const jokerSuit = ref<string>('rams');
const more = ref(false);
// an opposed duel (CRG p.10): flip as the attacker or the resister, so the cheating order can be followed
const duelRole = ref<'' | 'attacker' | 'resister'>('');
const duelTarget = ref<number | null>(null);
/** Attacking flips of the other side that nobody has resisted yet. */
const attackTargets = computed(() =>
    (state.value?.flips ?? []).filter((f) => f.duel?.role === 'attacker' && !f.duel.linked && f.owner_slot !== actingSlot.value),
);
async function flip() {
    const r = await run('flip', {
        modifier: modifier.value,
        label: label.value.trim() || undefined,
        duel_role: duelRole.value || undefined,
        duel_with: duelRole.value === 'resister' ? (duelTarget.value ?? attackTargets.value[0]?.id) : undefined,
    });
    if (!r.error) {
        modifier.value = 0;
        label.value = '';
        labelOpen.value = false;
        duelRole.value = '';
        duelTarget.value = null;
    }
}
const totalInput = ref<Record<number, number | string>>({});
const setTotal = (f: FateFlip) => run('total', { flip: f.id, total: Number(totalInput.value[f.id]), slot: f.owner_slot });
const modText = (m: number) => (m > 0 ? `+${m}` : m < 0 ? `${m}` : '0');
const modWords = (m: number) => (m > 0 ? `+${m}` : m < 0 ? `${m}` : 'plain flip');

const revealed = (f: FateFlip) => (state.value?.conflict ?? []).filter((c) => c.flip_id === f.id);
const isResult = (f: FateFlip, c: { owner_slot: number | null; card: number }) =>
    !!f.chosen && f.chosen.owner_slot === c.owner_slot && f.chosen.card === c.card;
const canChoose = (f: FateFlip, card: number) => !f.chosen && f.owner_slot === actingSlot.value && f.legal.includes(card);
const needsSuit = (f: FateFlip) => !f.chosen && f.owner_slot === actingSlot.value && f.legal.includes(RED_JOKER);
const canCheat = (f: FateFlip) => cheatable(f) && picked.value !== null;
/** A flip whose result I may still cheat (CRG p.9): it has one, I have not had my chance, a Black Joker never can be, and a Red Joker stops its own flipper. */
const cheatable = (f: FateFlip) =>
    !!f.chosen &&
    actingSlot.value !== null &&
    !f.cheaters.includes(actingSlot.value) &&
    f.chosen.card !== BLACK_JOKER &&
    !(f.chosen.card === RED_JOKER && f.owner_slot === actingSlot.value) &&
    // in a linked duel only the player whose turn it is may cheat: the lowest total first (the resister on a tie), then the other
    (!f.duel?.linked || f.duel.cheat_turn === actingSlot.value);
const cheatableFlips = computed(() => (state.value?.flips ?? []).filter(cheatable));
const canPass = (f: FateFlip) =>
    !!f.chosen && actingSlot.value !== null && !f.cheaters.includes(actingSlot.value) && (!f.duel?.linked || f.duel.cheat_turn === actingSlot.value);
const chooseCard = (f: FateFlip, card: number) => run('choose', { flip: f.id, card, suit: card === RED_JOKER ? jokerSuit.value : undefined });
async function cheat(f: FateFlip) {
    if (picked.value === null) return;
    const r = await run('cheat', { flip: f.id, card: picked.value, suit: picked.value === RED_JOKER ? jokerSuit.value : undefined });
    if (!r.error) picked.value = null;
}

// ----- effects: look at the top, search the deck -----
const peekN = ref(1);
const peeked = ref<number[]>([]);
const peekOpen = ref(false);
async function peek() {
    const r = await run('peek', { n: peekN.value });
    if (r.peeked?.length) {
        peeked.value = r.peeked;
        peekOpen.value = true;
    }
}
async function arrange(decisions: { card: number; to: string }[]) {
    const r = await run('arrange', { decisions });
    if (!r.error) peekOpen.value = false;
}
const searched = ref<number[]>([]);
const searchOpen = ref(false);
async function search() {
    const r = await run('search');
    if (r.deck) {
        searched.value = r.deck;
        searchOpen.value = true;
    }
}
async function take(card: number) {
    const r = await run('search.take', { card });
    if (!r.error) searchOpen.value = false;
}

// ----- what the other player has asked of me, and what I have asked of them -----
const prompts = computed(() => (state.value?.prompts ?? []).filter((q) => controlled.value.some((c) => c.slot === q.to_slot)));
const sent = computed(() => state.value?.sent ?? []);
const answers = computed(() => state.value?.answers ?? []);
const askerOf = (a: FateAnswer) => players.value.find((p) => p.slot !== a.owner_slot)?.slot ?? null;
const answer = (q: FatePrompt, show?: boolean) => props.feed.act('respond', { prompt: q.id, show, slot: q.to_slot });
const putAway = (a: FateAnswer) => props.feed.act('dismiss', { prompt: a.id, slot: askerOf(a) });
const promptText = (q: FatePrompt) =>
    q.kind === 'reveal' ? 'asks to see your hand' : `asks you to discard ${q.count} card${q.count === 1 ? '' : 's'} (you choose which)`;

// ----- effects on the other player's hand -----
const opponentSlot = computed(() => players.value.find((p) => p.slot !== actingSlot.value)?.slot ?? null);
const randomN = ref(1);
const demandN = ref(1);
const demandNote = ref('');
const randomDiscard = () => run('random_discard', { n: randomN.value });
const askDiscard = () => run('demand', { kind: 'discard', count: demandN.value, note: demandNote.value.trim() || undefined });
const askReveal = () => run('demand', { kind: 'reveal', note: demandNote.value.trim() || undefined });

/** Two flips with results at once is an opposed duel: the lowest total cheats first, the resister on a tie, then the other (CRG p.9). */
const duel = computed(
    () => (state.value?.flips ?? []).filter((f) => f.chosen).length >= 2 && (state.value?.flips ?? []).every((f) => !f.duel?.linked),
);

// ----- the history -----
const historyOpen = ref(false);
const lines = ref<FateLogLine[]>([]);
async function loadLog() {
    lines.value = (await props.feed.log?.()) ?? [];
}
function onHistoryToggle(event: Event) {
    historyOpen.value = (event.target as HTMLDetailsElement).open;
    if (historyOpen.value) void loadLog();
}
watch(state, () => historyOpen.value && void loadLog());

// ----- the start phase: discard any, then draw up to the maximum -----
const startOpen = ref(false);
async function startPhase(discard: number[], shuffle: boolean) {
    const r = await run('refill', { discard, shuffle, ...maxBody() });
    if (!r.error) startOpen.value = false;
}

// ----- a discard pile, opened whole -----
const pileSlot = ref<number | null>(null);
const pile = computed(() => players.value.find((p) => p.slot === pileSlot.value) ?? null);
const pileOpen = computed({
    get: () => pileSlot.value !== null,
    set: (v: boolean) => {
        if (!v) pileSlot.value = null;
    },
});
</script>

<template>
    <div class="space-y-3 text-sm" data-testid="fate-panel">
        <p
            v-if="feed.error.value"
            class="rounded-md border border-destructive/40 bg-destructive/10 p-2 text-destructive"
            role="alert"
            data-testid="fate-error"
        >
            {{ feed.error.value }}
        </p>
        <p v-if="!state" class="text-muted-foreground">Loading the Fate deck…</p>

        <template v-else>
            <!-- which side the mat is for, when I control more than one (a solo game, the sandbox) -->
            <div v-if="controlled.length > 1" class="flex flex-wrap items-center gap-1" role="group" aria-label="Acting as" data-testid="fate-acting">
                <span class="text-xs text-muted-foreground">Mat for</span>
                <Button
                    v-for="p in controlled"
                    :key="p.slot"
                    size="sm"
                    :variant="p.slot === actingSlot ? 'default' : 'outline'"
                    :aria-pressed="p.slot === actingSlot"
                    :data-testid="`fate-act-${p.slot}`"
                    @click="acting = p.slot"
                    >{{ nameOf(p.slot) }}</Button
                >
            </div>

            <!-- what the other player has asked of me -->
            <div
                v-for="q in prompts"
                :key="q.id"
                class="space-y-1.5 rounded-md border border-amber-500/50 bg-amber-500/10 p-2"
                role="alert"
                data-testid="fate-prompt"
            >
                <p>
                    <span class="font-medium">{{ nameOf(q.from_slot) }}</span> {{ promptText(q)
                    }}<span v-if="q.note" class="text-muted-foreground"> · {{ q.note }}</span>
                    <span v-if="controlled.length > 1" class="text-xs text-muted-foreground"> (for {{ nameOf(q.to_slot) }})</span>
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-if="q.kind === 'reveal'">
                        <Button size="sm" :disabled="busy" data-testid="fate-prompt-show" @click="answer(q, true)">Show my hand</Button>
                        <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-prompt-refuse" @click="answer(q, false)"
                            >Refuse</Button
                        >
                    </template>
                    <Button v-else size="sm" :disabled="busy" data-testid="fate-prompt-done" @click="answer(q)">Done: I discarded</Button>
                </div>
            </div>
            <p v-for="q in sent" :key="`s${q.id}`" class="text-xs text-muted-foreground" data-testid="fate-sent">
                Waiting for {{ nameOf(q.to_slot) }} {{ q.kind === 'reveal' ? 'to answer: show hand' : `to discard ${q.count}` }}.
            </p>
            <div
                v-for="a in answers"
                :key="`a${a.id}`"
                class="flex flex-wrap items-center gap-1.5 rounded-md border bg-muted/30 p-2"
                data-testid="fate-answer"
            >
                <span v-if="a.declined" class="text-xs">{{ nameOf(a.owner_slot) }} refused to show their hand.</span>
                <template v-else>
                    <span class="text-xs font-medium">{{ nameOf(a.owner_slot) }}'s hand:</span>
                    <FateCardFace v-for="c in a.cards ?? []" :key="c" :card="c" small />
                    <span v-if="!a.cards?.length" class="text-xs text-muted-foreground">empty</span>
                </template>
                <Button size="sm" variant="ghost" class="ml-auto" data-testid="fate-answer-dismiss" @click="putAway(a)">Put away</Button>
            </div>

            <!-- the other side(s): a thin strip each (everyone, for someone watching) -->
            <div
                v-for="p in strips"
                :key="p.slot"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border bg-muted/30 px-2 py-1.5"
                :data-testid="`fate-side-${p.slot}`"
            >
                <span class="min-w-0 truncate font-medium">{{ nameOf(p.slot) }}</span>
                <!-- their hand: a count, as overlapping card backs -->
                <span class="flex items-center pl-3" data-testid="fate-hand-backs" :aria-label="`${p.hand_count} cards in hand`">
                    <FateCardFace v-for="n in Math.min(p.hand_count, state.hand_max)" :key="n" back small class="-ml-3" />
                    <span v-if="!p.hand_count" class="pl-0 text-xs text-muted-foreground">no cards</span>
                </span>
                <span class="text-xs text-muted-foreground">
                    Hand <span data-testid="fate-hand-count">{{ p.hand_count }}/{{ state.hand_max }}</span> · Deck {{ deckSize(p) }}
                </span>
                <button
                    v-if="p.discard.length"
                    type="button"
                    class="ml-auto flex items-center gap-1 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    :data-testid="`fate-strip-pile-${p.slot}`"
                    @click="pileSlot = p.slot"
                >
                    <FateCardFace
                        :card="topOf(p)"
                        small
                        :label="`Look through ${nameOf(p.slot)}'s discard pile: ${p.discard.length} cards, top card ${cardLabel(topOf(p))}`"
                    />
                    <span class="text-xs text-muted-foreground">{{ p.discard.length }}</span>
                </button>
                <span v-else class="ml-auto text-xs text-muted-foreground">Discard empty</span>
            </div>

            <!-- the mat for the side I am acting as (nothing to act on for someone watching) -->
            <div
                v-if="mine"
                class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-2 rounded-lg border bg-gradient-to-b from-emerald-900/10 to-emerald-900/5 p-2"
                data-testid="fate-mat"
            >
                <!-- deck: tap to draw -->
                <div class="flex w-14 flex-col items-center gap-1">
                    <button
                        type="button"
                        class="relative rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-60"
                        :disabled="busy"
                        data-testid="fate-draw"
                        @click="run('draw', { n: 1 })"
                    >
                        <FateCardFace back large :label="`Draw a card, ${deckSize(mine)} in the deck`" />
                        <span
                            class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-primary px-1 text-center text-xs font-semibold text-primary-foreground"
                            aria-hidden="true"
                            data-testid="fate-deck-count"
                            >{{ deckSize(mine) }}</span
                        >
                    </button>
                    <span class="text-[0.7rem] uppercase tracking-wide text-muted-foreground">Deck</span>
                    <span class="-mt-1 text-center text-[0.65rem] leading-tight text-muted-foreground" data-testid="fate-draw-hint">tap to draw</span>
                </div>

                <!-- the conflict: flips in progress, and the flip control -->
                <div class="min-w-0 space-y-2" data-testid="fate-conflict-zone">
                    <p v-if="duel" class="rounded-md bg-muted/50 px-2 py-1 text-xs" data-testid="fate-duel-hint">
                        Two results at once? For an opposed duel flip as the attacker and the resister: each player enters their total and the
                        cheating order (lowest first, the resister on a tie) is followed for you.
                    </p>
                    <p v-if="!state.flips.length" class="rounded-md border border-dashed px-2 py-3 text-center text-xs text-muted-foreground">
                        Nothing flipped. Use +/- then Flip.
                    </p>
                    <section
                        v-for="f in state.flips"
                        :key="f.id"
                        class="space-y-1 rounded-md border bg-background/60 p-1.5"
                        :data-testid="`fate-flip-${f.id}`"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-x-2 text-xs">
                            <span class="font-medium">{{ nameOf(f.owner_slot) }}</span>
                            <span class="text-muted-foreground">{{ f.label ? `${f.label} · ` : '' }}{{ modWords(f.modifier) }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1" data-testid="fate-conflict">
                            <template v-for="c in revealed(f)" :key="`${c.owner_slot}-${c.card}`">
                                <button
                                    v-if="canChoose(f, c.card)"
                                    type="button"
                                    class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    data-testid="fate-choose"
                                    :data-card="c.card"
                                    :disabled="busy"
                                    @click="chooseCard(f, c.card)"
                                >
                                    <FateCardFace :card="c.card" note="take as the result" />
                                </button>
                                <FateCardFace
                                    v-else
                                    :card="c.card"
                                    :joker-suit="c.joker_suit"
                                    :highlight="isResult(f, c)"
                                    :dim="f.chosen ? !isResult(f, c) : !f.legal.includes(c.card)"
                                    data-testid="fate-conflict-card"
                                    :data-card="c.card"
                                />
                            </template>
                        </div>
                        <label v-if="needsSuit(f)" class="flex items-center gap-2 text-xs">
                            A Red Joker takes the suit
                            <select v-model="jokerSuit" class="rounded-md border bg-background px-1.5 py-1" data-testid="fate-joker-suit">
                                <option v-for="s in SUITS" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </label>
                        <p v-if="!f.chosen && f.owner_slot === actingSlot" class="text-xs text-muted-foreground" data-testid="fate-choose-hint">
                            {{
                                f.modifier > 0
                                    ? 'Tap the card to take.'
                                    : f.modifier < 0
                                      ? 'The lowest counts (you pick between ties).'
                                      : 'Tap the card.'
                            }}
                        </p>
                        <p v-if="f.chosen" class="text-xs" data-testid="fate-result">
                            Result <span class="font-semibold">{{ cardLabel(f.chosen.card) }}</span><span v-if="f.chosen.joker_suit">
                                as {{ f.chosen.joker_suit }}</span
                            >
                            <span v-if="f.cheaters.length" class="text-muted-foreground">
                                · passed or cheated: {{ f.cheaters.map(nameOf).join(', ') }}</span
                            >
                            <span v-if="revealed(f).length > 1" class="text-muted-foreground" data-testid="fate-result-all">
                                · Done discards all {{ revealed(f).length }} cards</span
                            >
                        </p>
                        <div v-if="f.duel" class="space-y-1 text-xs" data-testid="fate-duel">
                            <p class="font-medium">
                                Opposed duel, {{ f.duel.role
                                }}<span v-if="!f.duel.linked" class="font-normal text-muted-foreground"> (not yet resisted)</span>
                            </p>
                            <div v-if="f.chosen && f.owner_slot === actingSlot" class="flex flex-wrap items-center gap-1">
                                <label class="flex items-center gap-1">
                                    Your total
                                    <input
                                        v-model.number="totalInput[f.id]"
                                        type="number"
                                        min="-10"
                                        max="60"
                                        class="w-16 rounded-md border bg-background px-2 py-1"
                                        :aria-label="`Duel total for ${nameOf(f.owner_slot)}`"
                                        data-testid="fate-duel-total"
                                    />
                                </label>
                                <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-duel-total-save" @click="setTotal(f)"
                                    >Set total</Button
                                >
                            </div>
                            <p v-if="f.duel.total !== null" data-testid="fate-duel-totals">
                                Total {{ f.duel.total }}<span v-if="f.duel.opponent_total !== null"> against {{ f.duel.opponent_total }}</span>
                            </p>
                            <p v-if="f.duel.cheat_turn !== null" class="font-medium text-primary" data-testid="fate-duel-turn">
                                {{ f.duel.cheat_turn === actingSlot ? 'Your' : `${nameOf(f.duel.cheat_turn)}'s` }} chance to cheat Fate (the lowest
                                total goes first)
                            </p>
                            <p v-else-if="f.duel.linked && f.chosen" class="text-muted-foreground">Both players enter their total before cheating.</p>
                        </div>
                        <p v-if="cheatable(f) && picked === null" class="text-xs text-muted-foreground" data-testid="fate-cheat-hint">
                            You may cheat Fate: tap a card in your hand, then choose Cheat.
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <Button v-if="canCheat(f)" size="sm" variant="outline" :disabled="busy" data-testid="fate-cheat" @click="cheat(f)"
                                >Cheat with {{ picked !== null ? cardLabel(picked) : 'the picked card' }}</Button
                            >
                            <Button
                                v-if="canPass(f)"
                                size="sm"
                                variant="ghost"
                                :disabled="busy"
                                data-testid="fate-pass"
                                @click="run('cheat.decline', { flip: f.id })"
                                >Pass</Button
                            >
                            <Button
                                v-if="f.owner_slot === actingSlot"
                                size="sm"
                                :disabled="busy || !f.chosen"
                                data-testid="fate-resolve"
                                @click="run('resolve', { flip: f.id })"
                                >Done: discard</Button
                            >
                        </div>
                    </section>

                    <!-- flip control: one small bar -->
                    <div class="flex flex-wrap items-center gap-1" data-testid="fate-flip-row">
                        <Button
                            size="sm"
                            variant="outline"
                            class="px-2"
                            aria-label="One more minus"
                            :disabled="busy || modifier <= -3"
                            data-testid="fate-minus"
                            @click="modifier--"
                            ><Minus class="size-4"
                        /></Button>
                        <span class="min-w-8 text-center font-semibold tabular-nums" data-testid="fate-modifier" :title="modWords(modifier)">{{
                            modText(modifier)
                        }}</span>
                        <Button
                            size="sm"
                            variant="outline"
                            class="px-2"
                            aria-label="One more plus"
                            :disabled="busy || modifier >= 3"
                            data-testid="fate-plus"
                            @click="modifier++"
                            ><Plus class="size-4"
                        /></Button>
                        <select
                            v-model="duelRole"
                            class="rounded-md border bg-background px-1.5 py-1 text-xs"
                            aria-label="Flip as part of an opposed duel"
                            title="An opposed duel: flip as the attacker, or as the resister of the other player's attack"
                            data-testid="fate-duel-role"
                        >
                            <option value="">No duel</option>
                            <option value="attacker">Duel: attack</option>
                            <option value="resister" :disabled="!attackTargets.length">Duel: resist</option>
                        </select>
                        <select
                            v-if="duelRole === 'resister' && attackTargets.length > 1"
                            v-model="duelTarget"
                            class="rounded-md border bg-background px-1.5 py-1 text-xs"
                            aria-label="Which attack to resist"
                            data-testid="fate-duel-target"
                        >
                            <option v-for="t in attackTargets" :key="t.id" :value="t.id">
                                {{ nameOf(t.owner_slot) }}'s {{ t.label ?? 'attack' }}
                            </option>
                        </select>
                        <Button size="sm" :disabled="busy" class="flex-1" data-testid="fate-flip" @click="flip">Flip</Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="px-2 text-xs"
                            :aria-expanded="labelOpen"
                            title="Say what the flip is for"
                            data-testid="fate-label-toggle"
                            @click="labelOpen = !labelOpen"
                            >{{ label ? 'Label ✓' : 'Label' }}</Button
                        >
                    </div>
                    <input
                        v-if="labelOpen"
                        v-model="label"
                        type="text"
                        maxlength="60"
                        placeholder="What is this flip for? (optional)"
                        class="w-full rounded-md border bg-background px-2 py-1.5"
                        aria-label="What this flip is for"
                        data-testid="fate-label"
                    />
                </div>

                <!-- my discard pile: the top card; tap to look through it -->
                <div class="flex w-14 flex-col items-center gap-1" :data-testid="`fate-discard-${mine.slot}`">
                    <button
                        type="button"
                        class="relative rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :aria-label="mine.discard.length ? undefined : 'Discard pile, empty. Look through it'"
                        data-testid="fate-pile-open"
                        @click="pileSlot = mine.slot"
                    >
                        <FateCardFace
                            v-if="mine.discard.length"
                            :card="topOf(mine)"
                            large
                            :label="`Look through the discard pile: ${mine.discard.length} cards, top card ${cardLabel(topOf(mine))}`"
                            data-testid="fate-discard-card"
                            :data-card="topOf(mine)"
                        />
                        <span
                            v-else
                            class="flex h-[4.5rem] w-[3.25rem] items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground"
                            >Empty</span
                        >
                        <span
                            v-if="mine.discard.length"
                            class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-secondary px-1 text-center text-xs font-semibold text-secondary-foreground"
                            >{{ mine.discard.length }}</span
                        >
                    </button>
                    <span class="text-[0.7rem] uppercase tracking-wide text-muted-foreground">Discard</span>
                </div>
            </div>

            <div v-if="mine" class="flex flex-wrap items-center gap-1.5" data-testid="fate-draw-row">
                <span class="text-xs text-muted-foreground">Draw</span>
                <div class="flex items-center" role="group" aria-label="Draw up to the hand limit">
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-r-none px-2"
                        aria-label="Lower the hand limit"
                        title="A master or effect that shrinks your hand: lower the limit"
                        :disabled="busy || handLimit <= 1"
                        data-testid="fate-limit-minus"
                        @click="changeLimit(-1)"
                        ><Minus class="size-3.5"
                    /></Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-none border-x-0"
                        :disabled="busy"
                        :title="`Draw until the hand is at ${handLimit}`"
                        data-testid="fate-refill"
                        @click="run('refill', { discard: [], ...maxBody() })"
                        >Up to {{ handLimit }}</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-l-none px-2"
                        aria-label="Raise the hand limit"
                        title="Back towards the game's limit"
                        :disabled="busy || !lowered"
                        data-testid="fate-limit-plus"
                        @click="changeLimit(1)"
                        ><Plus class="size-3.5"
                    /></Button>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="busy"
                    title="Start phase: discard any cards, then draw up to the hand maximum"
                    data-testid="fate-start-phase"
                    @click="startOpen = true"
                    >Start phase: discard, then draw</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="busy"
                    title="Put your discard pile back into your deck and shuffle it"
                    data-testid="fate-shuffle-quick"
                    @click="run('shuffle')"
                    >Shuffle</Button
                >
                <Button
                    v-if="state.can_undo !== undefined"
                    size="sm"
                    variant="ghost"
                    :disabled="busy || !state.can_undo"
                    title="Take back the last thing done to the deck: a draw, a flip, a discard, a shuffle"
                    data-testid="fate-undo"
                    @click="run('undo')"
                    ><Undo2 class="mr-1 size-3.5" />Undo</Button
                >
            </div>

            <!-- my hand, along the bottom -->
            <section v-if="mine" class="space-y-1.5" :data-testid="`fate-side-${mine.slot}`">
                <div class="flex items-baseline justify-between">
                    <span class="font-medium">{{ nameOf(mine.slot) }}'s hand</span>
                    <span class="text-xs text-muted-foreground">
                        <span :class="overMax ? 'font-semibold text-amber-600 dark:text-amber-400' : ''" data-testid="fate-hand-count"
                            >{{ mine.hand_count }}/{{ handLimit }}</span
                        >
                    </span>
                </div>
                <p v-if="overMax" class="text-xs text-amber-600 dark:text-amber-400" data-testid="fate-over-max">
                    {{ overMax }} over the hand limit: discard down at the end of the action.
                </p>
                <div ref="handEl" class="flex min-h-[5.5rem] pb-1 pt-2" data-testid="fate-hand">
                    <button
                        v-for="(c, i) in mine.hand"
                        :key="c"
                        type="button"
                        class="relative shrink-0 rounded-md transition-transform focus-visible:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :class="picked === c ? 'z-10 -translate-y-1.5' : ''"
                        :style="{ marginLeft: i === 0 ? '0' : `${handStep - HAND_CARD_W}px` }"
                        :aria-pressed="picked === c"
                        data-testid="fate-hand-card"
                        :data-card="c"
                        :data-slot="mine.slot"
                        @click="pick(c)"
                    >
                        <FateCardFace :card="c" large note="in hand" :highlight="picked === c" :corner="handOverlaps" />
                    </button>
                    <span v-if="!mine.hand?.length" class="py-5 text-xs text-muted-foreground">No cards in hand. Tap the deck to draw.</span>
                </div>

                <!-- what to do with the picked card -->
                <div
                    v-if="picked !== null"
                    class="flex flex-wrap items-center gap-1 rounded-md border border-primary/40 bg-primary/5 p-1.5"
                    data-testid="fate-card-menu"
                >
                    <span class="mr-1 text-xs font-medium">{{ cardLabel(picked) }}</span>
                    <Button size="sm" :disabled="busy" data-testid="fate-menu-discard" @click="handAct('discard', { cards: [picked] })"
                        >Discard</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        data-testid="fate-menu-top"
                        @click="handAct('put', { card: picked, where: 'top' })"
                        >Top of deck</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        data-testid="fate-menu-bottom"
                        @click="handAct('put', { card: picked, where: 'bottom' })"
                        >Bottom of deck</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        data-testid="fate-menu-remove"
                        @click="handAct('remove', { cards: [picked] })"
                        >Remove</Button
                    >
                    <!-- Cheat Fate: replace a flip's result with this card (the old result is discarded) -->
                    <div class="flex w-full flex-wrap items-center gap-1 border-t pt-1.5" data-testid="fate-cheat-menu">
                        <Button
                            v-for="f in cheatableFlips"
                            :key="f.id"
                            size="sm"
                            variant="secondary"
                            :disabled="busy"
                            data-testid="fate-menu-cheat"
                            @click="cheat(f)"
                            >Cheat Fate: replace {{ nameOf(f.owner_slot) }}'s {{ cardLabel(f.chosen!.card) }}</Button
                        >
                        <label v-if="picked === RED_JOKER && cheatableFlips.length" class="flex items-center gap-1 text-xs">
                            A Red Joker takes the suit
                            <select v-model="jokerSuit" class="rounded-md border bg-background px-1.5 py-1" data-testid="fate-cheat-suit">
                                <option v-for="suit in SUITS" :key="suit" :value="suit">{{ suit }}</option>
                            </select>
                        </label>
                        <span v-if="!cheatableFlips.length" class="text-xs text-muted-foreground" data-testid="fate-cheat-none">
                            {{
                                state.flips.length
                                    ? 'No flip result you can cheat now (a Black Joker cannot be cheated, and you get one chance per flip).'
                                    : 'Cheat Fate: once a flip has a result you can replace it with a card from your hand.'
                            }}
                        </span>
                    </div>
                </div>

                <!-- effects -->
                <div class="flex items-center justify-end">
                    <Button size="sm" variant="ghost" :aria-expanded="more" data-testid="fate-more" @click="more = !more"
                        >Effects <ChevronDown class="ml-1 size-4 transition-transform" :class="more ? 'rotate-180' : ''"
                    /></Button>
                </div>
                <div v-if="more" class="flex flex-wrap items-center gap-1.5 rounded-md border p-2" data-testid="fate-effects">
                    <label class="flex items-center gap-1">
                        Look at top
                        <input
                            v-model.number="peekN"
                            type="number"
                            min="1"
                            max="20"
                            class="w-14 rounded-md border bg-background px-2 py-1"
                            aria-label="How many cards to look at"
                            data-testid="fate-peek-n"
                        />
                    </label>
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-peek" @click="peek">Look</Button>
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-search" @click="search">Search the deck</Button>
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-shuffle" @click="run('shuffle')"
                        >Shuffle discard in</Button
                    >
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-end-phase" @click="run('end_phase')"
                        >End phase: shuffle both discards in</Button
                    >
                    <div v-if="opponentSlot !== null" class="w-full space-y-1.5 border-t pt-2" data-testid="fate-opponent-effects">
                        <p class="text-xs font-medium text-muted-foreground">On {{ nameOf(opponentSlot) }}'s hand</p>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <input
                                v-model.number="randomN"
                                type="number"
                                min="1"
                                max="7"
                                class="w-14 rounded-md border bg-background px-2 py-1"
                                aria-label="How many cards"
                                data-testid="fate-random-n"
                            />
                            <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-random-discard" @click="randomDiscard"
                                >Make them discard at random</Button
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <input
                                v-model.number="demandN"
                                type="number"
                                min="1"
                                max="7"
                                class="w-14 rounded-md border bg-background px-2 py-1"
                                aria-label="How many cards"
                                data-testid="fate-demand-n"
                            />
                            <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-ask-discard" @click="askDiscard"
                                >Ask them to discard (their choice)</Button
                            >
                            <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-ask-reveal" @click="askReveal"
                                >Ask to see their hand</Button
                            >
                        </div>
                        <input
                            v-model="demandNote"
                            type="text"
                            maxlength="80"
                            placeholder="Which effect? (optional)"
                            class="w-full rounded-md border bg-background px-2 py-1.5"
                            aria-label="Which effect is asking"
                            data-testid="fate-demand-note"
                        />
                    </div>
                </div>
            </section>

            <!-- someone watching sees the conflict too, since a flip is public -->
            <section v-if="!mine && state.flips.length" class="space-y-1" data-testid="fate-watch-flips">
                <div v-for="f in state.flips" :key="f.id" class="space-y-1 rounded-md border p-1.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2 text-xs">
                        <span class="font-medium">{{ nameOf(f.owner_slot) }} flips</span>
                        <span class="text-muted-foreground">{{ f.label ? `${f.label} · ` : '' }}{{ modWords(f.modifier) }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1" data-testid="fate-conflict">
                        <FateCardFace
                            v-for="c in revealed(f)"
                            :key="`${c.owner_slot}-${c.card}`"
                            :card="c.card"
                            :joker-suit="c.joker_suit"
                            :highlight="isResult(f, c)"
                            data-testid="fate-conflict-card"
                            :data-card="c.card"
                        />
                    </div>
                    <p v-if="f.chosen" class="text-xs" data-testid="fate-result">
                        Result <span class="font-semibold">{{ cardLabel(f.chosen.card) }}</span><span v-if="f.chosen.joker_suit">
                            as {{ f.chosen.joker_suit }}</span
                        >
                    </p>
                </div>
            </section>
            <p v-if="!mine && !state.flips.length" class="text-xs text-muted-foreground">Nothing flipped right now.</p>

            <!-- what has happened at the table, in words -->
            <details class="rounded-md border" data-testid="fate-history" @toggle="onHistoryToggle">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground">History</summary>
                <ol v-if="lines.length" class="max-h-48 space-y-1 overflow-y-auto border-t p-2 text-xs" data-testid="fate-history-lines">
                    <li v-for="l in lines" :key="l.id">{{ l.text }}</li>
                </ol>
                <p v-else class="border-t p-2 text-xs text-muted-foreground">Nothing yet.</p>
            </details>

            <!-- cards set aside for good -->
            <div
                v-for="p in players.filter((x) => x.removed.length)"
                :key="`r${p.slot}`"
                class="flex flex-wrap items-center gap-1"
                data-testid="fate-removed"
            >
                <span class="text-xs text-muted-foreground">{{ nameOf(p.slot) }}, removed from the game:</span>
                <FateCardFace v-for="c in p.removed" :key="c" :card="c" small />
            </div>
        </template>

        <FatePeekDialog v-model:open="peekOpen" :cards="peeked" :busy="busy" @arrange="arrange" />
        <FateSearchDialog v-model:open="searchOpen" :cards="searched" :busy="busy" @take="take" />
        <FateStartDialog v-model:open="startOpen" :hand="mine?.hand ?? []" :hand-max="handLimit" :busy="busy" @apply="startPhase" />
        <FatePileDialog
            v-model:open="pileOpen"
            :title="`${nameOf(pileSlot)}'s discard pile`"
            :cards="pile?.discard ?? []"
            :can-act="pileSlot !== null && pileSlot === actingSlot"
            :busy="busy"
            @act="(name, body) => run(name, body)"
        />
    </div>
</template>
