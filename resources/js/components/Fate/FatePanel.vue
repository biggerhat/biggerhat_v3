<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import FatePeekDialog from '@/components/Fate/FatePeekDialog.vue';
import FatePileDialog from '@/components/Fate/FatePileDialog.vue';
import FateSearchDialog from '@/components/Fate/FateSearchDialog.vue';
import { Button } from '@/components/ui/button';
import { BLACK_JOKER, COUNT, RED_JOKER, SUITS, cardLabel, type FateFeed, type FateFlip, type FatePlayer } from '@/lib/fate/fate';
import { ChevronDown, Minus, Plus } from 'lucide-vue-next';
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

const run = (name: string, body: Record<string, unknown> = {}) => props.feed.act(name, { slot: actingSlot.value, ...body });

// ----- a hand card picked for the action bar -----
const picked = ref<number | null>(null);
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
async function flip() {
    const r = await run('flip', { modifier: modifier.value, label: label.value.trim() || undefined });
    if (!r.error) {
        modifier.value = 0;
        label.value = '';
        labelOpen.value = false;
    }
}
const modText = (m: number) => (m > 0 ? `+${m}` : m < 0 ? `${m}` : '0');
const modWords = (m: number) => (m > 0 ? `+${m}` : m < 0 ? `${m}` : 'plain flip');

const revealed = (f: FateFlip) => (state.value?.conflict ?? []).filter((c) => c.flip_id === f.id);
const isResult = (f: FateFlip, c: { owner_slot: number | null; card: number }) =>
    !!f.chosen && f.chosen.owner_slot === c.owner_slot && f.chosen.card === c.card;
const canChoose = (f: FateFlip, card: number) => !f.chosen && f.owner_slot === actingSlot.value && f.legal.includes(card);
const needsSuit = (f: FateFlip) => !f.chosen && f.owner_slot === actingSlot.value && f.legal.includes(RED_JOKER);
const canCheat = (f: FateFlip) =>
    !!f.chosen && actingSlot.value !== null && !f.cheaters.includes(actingSlot.value) && picked.value !== null && f.chosen.card !== BLACK_JOKER;
const canPass = (f: FateFlip) => !!f.chosen && actingSlot.value !== null && !f.cheaters.includes(actingSlot.value);
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
                    :aria-label="`Look through ${nameOf(p.slot)}'s discard pile (${p.discard.length})`"
                    :data-testid="`fate-strip-pile-${p.slot}`"
                    @click="pileSlot = p.slot"
                >
                    <FateCardFace :card="topOf(p)" small />
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
                        aria-label="Draw a card"
                        data-testid="fate-draw"
                        @click="run('draw', { n: 1 })"
                    >
                        <FateCardFace back large />
                        <span
                            class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-primary px-1 text-center text-xs font-semibold text-primary-foreground"
                            data-testid="fate-deck-count"
                            >{{ deckSize(mine) }}</span
                        >
                    </button>
                    <span class="text-[0.7rem] uppercase tracking-wide text-muted-foreground">Deck</span>
                    <button
                        type="button"
                        class="rounded border px-1.5 py-0.5 text-[0.7rem] hover:bg-muted disabled:opacity-60"
                        :disabled="busy"
                        :title="`Draw until the hand is at ${state.hand_max}`"
                        data-testid="fate-refill"
                        @click="run('refill', { discard: [] })"
                    >
                        Up to {{ state.hand_max }}
                    </button>
                </div>

                <!-- the conflict: flips in progress, and the flip control -->
                <div class="min-w-0 space-y-2" data-testid="fate-conflict-zone">
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
                                    :aria-label="`Take ${cardLabel(c.card)} as the result`"
                                    data-testid="fate-choose"
                                    :data-card="c.card"
                                    :disabled="busy"
                                    @click="chooseCard(f, c.card)"
                                >
                                    <FateCardFace :card="c.card" />
                                </button>
                                <FateCardFace
                                    v-else
                                    :card="c.card"
                                    :joker-suit="c.joker_suit"
                                    :highlight="isResult(f, c)"
                                    :dim="!f.chosen && !f.legal.includes(c.card)"
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
                            Result <span class="font-semibold">{{ cardLabel(f.chosen.card) }}</span
                            ><span v-if="f.chosen.joker_suit"> as {{ f.chosen.joker_suit }}</span>
                            <span v-if="f.cheaters.length" class="text-muted-foreground">
                                · passed or cheated: {{ f.cheaters.map(nameOf).join(', ') }}</span
                            >
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
                        :aria-label="`Look through the discard pile (${mine.discard.length})`"
                        data-testid="fate-pile-open"
                        @click="pileSlot = mine.slot"
                    >
                        <FateCardFace v-if="mine.discard.length" :card="topOf(mine)" large data-testid="fate-discard-card" :data-card="topOf(mine)" />
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

            <!-- my hand, along the bottom -->
            <section v-if="mine" class="space-y-1.5" :data-testid="`fate-side-${mine.slot}`">
                <div class="flex items-baseline justify-between">
                    <span class="font-medium">{{ nameOf(mine.slot) }}'s hand</span>
                    <span class="text-xs text-muted-foreground">
                        <span :class="mine.over_max ? 'font-semibold text-amber-600 dark:text-amber-400' : ''" data-testid="fate-hand-count"
                            >{{ mine.hand_count }}/{{ state.hand_max }}</span
                        >
                    </span>
                </div>
                <p v-if="mine.over_max" class="text-xs text-amber-600 dark:text-amber-400" data-testid="fate-over-max">
                    {{ mine.over_max }} over the hand limit: discard down at the end of the action.
                </p>
                <div class="-mx-1 flex snap-x gap-1.5 overflow-x-auto px-1 pb-1 pt-2" data-testid="fate-hand">
                    <button
                        v-for="c in mine.hand"
                        :key="c"
                        type="button"
                        class="snap-start rounded-md transition-transform focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :class="picked === c ? '-translate-y-1.5' : ''"
                        :aria-label="`${cardLabel(c)}, in hand`"
                        :aria-pressed="picked === c"
                        data-testid="fate-hand-card"
                        :data-card="c"
                        :data-slot="mine.slot"
                        @click="pick(c)"
                    >
                        <FateCardFace :card="c" large :highlight="picked === c" />
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
                    <span v-if="state.flips.some((f) => f.chosen)" class="text-xs text-muted-foreground">To cheat a flip, use the button on it.</span>
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
                        Result <span class="font-semibold">{{ cardLabel(f.chosen.card) }}</span
                        ><span v-if="f.chosen.joker_suit"> as {{ f.chosen.joker_suit }}</span>
                    </p>
                </div>
            </section>
            <p v-if="!mine && !state.flips.length" class="text-xs text-muted-foreground">Nothing flipped right now.</p>

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
