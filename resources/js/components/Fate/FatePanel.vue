<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import FatePeekDialog from '@/components/Fate/FatePeekDialog.vue';
import FateSearchDialog from '@/components/Fate/FateSearchDialog.vue';
import { Button } from '@/components/ui/button';
import { BLACK_JOKER, COUNT, RED_JOKER, SUITS, cardLabel, type FateFeed, type FateFlip } from '@/lib/fate/fate';
import { ChevronDown, Minus, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * The Fate deck at the table: each side's deck, hand and discard pile, the flip in progress, and the actions that change them.
 *
 * It is fed by a `feed`: a game's server deck (useFateGame) or the sandbox's practice deck (useLocalFate); both answer the same way,
 * so this component does not know which it has. A hand is drawn only for a side the viewer controls (the state carries no cards for
 * any other hand); the other side's hand is a row of card backs, as many as it holds.
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
const nameOf = (slot: number | null) => (slot === null ? 'Someone' : (props.names?.[slot] ?? `Player ${slot}`));
const deckSize = (p: { deck_count: number }) => (state.value?.started ? p.deck_count : COUNT);

const run = (name: string, body: Record<string, unknown> = {}) => props.feed.act(name, { slot: actingSlot.value, ...body });

// ----- a card picked for a menu (a hand card or a discard-pile card of a side I control) -----
type Pick = { slot: number; card: number; zone: 'hand' | 'discard' };
const picked = ref<Pick | null>(null);
function pick(slot: number, card: number, zone: Pick['zone']) {
    if (!controlled.value.some((p) => p.slot === slot)) return;
    acting.value = slot;
    picked.value =
        picked.value && picked.value.slot === slot && picked.value.card === card && picked.value.zone === zone ? null : { slot, card, zone };
}
// forget the pick when that card is no longer where it was
watch(state, (s) => {
    const p = picked.value;
    if (!p) return;
    const side = s?.players.find((x) => x.slot === p.slot);
    const where = p.zone === 'hand' ? side?.hand : side?.discard;
    if (!where?.includes(p.card)) picked.value = null;
});
async function menu(name: string, body: Record<string, unknown>) {
    const r = await run(name, body);
    if (!r.error) picked.value = null;
}

// ----- drawing, flipping -----
const modifier = ref(0);
const label = ref('');
const jokerSuit = ref<string>('rams');
const more = ref(false);
async function flip() {
    const r = await run('flip', { modifier: modifier.value, label: label.value.trim() || undefined });
    if (!r.error) {
        modifier.value = 0;
        label.value = '';
    }
}
const modText = (m: number) => (m > 0 ? `+${m}` : m < 0 ? `${m}` : 'no +/-');

const revealed = (f: FateFlip) => (state.value?.conflict ?? []).filter((c) => c.flip_id === f.id);
const isResult = (f: FateFlip, c: { owner_slot: number | null; card: number }) =>
    !!f.chosen && f.chosen.owner_slot === c.owner_slot && f.chosen.card === c.card;
const canChoose = (f: FateFlip, card: number) => !f.chosen && f.owner_slot === actingSlot.value && f.legal.includes(card);
const needsSuit = (f: FateFlip) => !f.chosen && f.owner_slot === actingSlot.value && f.legal.includes(RED_JOKER);
const canCheat = (f: FateFlip) =>
    !!f.chosen &&
    actingSlot.value !== null &&
    !f.cheaters.includes(actingSlot.value) &&
    picked.value?.zone === 'hand' &&
    picked.value.slot === actingSlot.value &&
    f.chosen.card !== BLACK_JOKER;
const canPass = (f: FateFlip) => !!f.chosen && actingSlot.value !== null && !f.cheaters.includes(actingSlot.value);
const chooseCard = (f: FateFlip, card: number) => run('choose', { flip: f.id, card, suit: card === RED_JOKER ? jokerSuit.value : undefined });
async function cheat(f: FateFlip) {
    if (!picked.value) return;
    const r = await run('cheat', { flip: f.id, card: picked.value.card, suit: picked.value.card === RED_JOKER ? jokerSuit.value : undefined });
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
const newestFirst = (cards: number[]) => [...cards].reverse();
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
            <!-- which side is acting, when I control more than one (a solo game, the sandbox) -->
            <div v-if="controlled.length > 1" class="flex flex-wrap items-center gap-1" role="group" aria-label="Acting as" data-testid="fate-acting">
                <span class="text-xs text-muted-foreground">Acting as</span>
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

            <!-- each side: counts, and the hand -->
            <section v-for="p in players" :key="p.slot" class="space-y-1.5 rounded-md border p-2" :data-testid="`fate-side-${p.slot}`">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <span class="font-medium">{{ nameOf(p.slot) }}</span>
                    <span class="text-xs text-muted-foreground">
                        Deck <span data-testid="fate-deck-count">{{ deckSize(p) }}</span> · Hand
                        <span :class="p.over_max ? 'font-semibold text-amber-600 dark:text-amber-400' : ''" data-testid="fate-hand-count"
                            >{{ p.hand_count }}/{{ state.hand_max }}</span
                        >
                        · Discard {{ p.discard.length }}
                    </span>
                </div>
                <p v-if="p.over_max && p.hand" class="text-xs text-amber-600 dark:text-amber-400" data-testid="fate-over-max">
                    {{ p.over_max }} over the hand limit: discard down at the end of the action.
                </p>
                <div v-if="p.hand" class="flex flex-wrap gap-1" data-testid="fate-hand">
                    <button
                        v-for="c in p.hand"
                        :key="c"
                        type="button"
                        class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :disabled="readOnly"
                        :aria-label="`${cardLabel(c)}, in hand`"
                        data-testid="fate-hand-card"
                        :data-card="c"
                        :data-slot="p.slot"
                        @click="pick(p.slot, c, 'hand')"
                    >
                        <FateCardFace :card="c" :highlight="picked?.slot === p.slot && picked.zone === 'hand' && picked.card === c" />
                    </button>
                    <span v-if="!p.hand.length" class="py-3 text-xs text-muted-foreground">No cards in hand.</span>
                </div>
                <!-- someone else's hand: a count, as card backs -->
                <div v-else class="flex flex-wrap items-center gap-1" data-testid="fate-hand-backs" :aria-label="`${p.hand_count} cards in hand`">
                    <FateCardFace v-for="n in Math.min(p.hand_count, state.hand_max)" :key="n" back small />
                    <span v-if="p.hand_count > state.hand_max" class="text-xs text-muted-foreground">+{{ p.hand_count - state.hand_max }}</span>
                    <span v-if="!p.hand_count" class="text-xs text-muted-foreground">Empty hand.</span>
                </div>
            </section>

            <!-- what to do with the picked card -->
            <div v-if="picked" class="space-y-1.5 rounded-md border border-primary/40 bg-primary/5 p-2" data-testid="fate-card-menu">
                <div class="flex items-center gap-2">
                    <FateCardFace :card="picked.card" small />
                    <span class="font-medium">{{ cardLabel(picked.card) }}</span>
                    <span class="text-xs text-muted-foreground">{{ picked.zone === 'hand' ? 'in hand' : 'in the discard pile' }}</span>
                </div>
                <div class="flex flex-wrap gap-1">
                    <Button
                        v-if="picked.zone === 'hand'"
                        size="sm"
                        :disabled="busy"
                        data-testid="fate-menu-discard"
                        @click="menu('discard', { cards: [picked.card] })"
                        >Discard</Button
                    >
                    <Button
                        v-else
                        size="sm"
                        :disabled="busy"
                        data-testid="fate-menu-draw-back"
                        @click="menu('draw_from_discard', { card: picked.card })"
                        >Take into hand</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        data-testid="fate-menu-top"
                        @click="menu('put', { card: picked.card, where: 'top' })"
                        >Top of deck</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        data-testid="fate-menu-bottom"
                        @click="menu('put', { card: picked.card, where: 'bottom' })"
                        >Bottom of deck</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="busy"
                        data-testid="fate-menu-remove"
                        @click="menu('remove', { cards: [picked.card] })"
                        >Remove from game</Button
                    >
                </div>
            </div>

            <!-- the actions -->
            <div v-if="controlled.length" class="space-y-2">
                <div class="flex flex-wrap items-center gap-1.5">
                    <Button size="sm" :disabled="busy" data-testid="fate-draw" @click="run('draw', { n: 1 })">Draw 1</Button>
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-refill" @click="run('refill', { discard: [] })"
                        >Draw up to {{ state.hand_max }}</Button
                    >
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-shuffle" @click="run('shuffle')"
                        >Shuffle discard in</Button
                    >
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
                    <Button size="sm" variant="outline" :disabled="busy" data-testid="fate-end-phase" @click="run('end_phase')"
                        >End phase: shuffle both discards in</Button
                    >
                </div>

                <!-- flip -->
                <div class="flex flex-wrap items-center gap-1.5" data-testid="fate-flip-row">
                    <Button
                        size="sm"
                        variant="outline"
                        aria-label="One more minus"
                        :disabled="busy || modifier <= -3"
                        data-testid="fate-minus"
                        @click="modifier--"
                        ><Minus class="size-4"
                    /></Button>
                    <span class="min-w-14 text-center font-medium" data-testid="fate-modifier">{{ modText(modifier) }}</span>
                    <Button
                        size="sm"
                        variant="outline"
                        aria-label="One more plus"
                        :disabled="busy || modifier >= 3"
                        data-testid="fate-plus"
                        @click="modifier++"
                        ><Plus class="size-4"
                    /></Button>
                    <input
                        v-model="label"
                        type="text"
                        maxlength="60"
                        placeholder="What for (optional)"
                        class="min-w-0 flex-1 rounded-md border bg-background px-2 py-1.5"
                        aria-label="What this flip is for"
                        data-testid="fate-label"
                    />
                    <Button size="sm" :disabled="busy" data-testid="fate-flip" @click="flip">Flip</Button>
                </div>
            </div>

            <!-- flips in progress -->
            <section v-for="f in state.flips" :key="f.id" class="space-y-1.5 rounded-md border p-2" :data-testid="`fate-flip-${f.id}`">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-medium">{{ nameOf(f.owner_slot) }} flips</span>
                    <span class="text-xs text-muted-foreground">{{ f.label ? `${f.label} · ` : '' }}{{ modText(f.modifier) }}</span>
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
                            ? 'Take any one card as the result.'
                            : f.modifier < 0
                              ? 'The lowest card is the result (you pick between ties).'
                              : 'Take the card.'
                    }}
                </p>
                <p v-if="f.chosen" class="text-xs" data-testid="fate-result">
                    Result: <span class="font-semibold">{{ cardLabel(f.chosen.card) }}</span
                    ><span v-if="f.chosen.joker_suit"> as {{ f.chosen.joker_suit }}</span>
                    <span v-if="f.cheaters.length" class="text-muted-foreground"> · passed or cheated: {{ f.cheaters.map(nameOf).join(', ') }}</span>
                </p>
                <div v-if="controlled.length" class="flex flex-wrap gap-1">
                    <Button v-if="canCheat(f)" size="sm" variant="outline" :disabled="busy" data-testid="fate-cheat" @click="cheat(f)"
                        >Cheat with {{ picked ? cardLabel(picked.card) : 'the picked card' }}</Button
                    >
                    <Button
                        v-if="canPass(f)"
                        size="sm"
                        variant="ghost"
                        :disabled="busy"
                        data-testid="fate-pass"
                        @click="run('cheat.decline', { flip: f.id })"
                        >Pass on cheating</Button
                    >
                    <Button
                        v-if="f.owner_slot === actingSlot"
                        size="sm"
                        :disabled="busy || !f.chosen"
                        data-testid="fate-resolve"
                        @click="run('resolve', { flip: f.id })"
                        >Resolve: discard these</Button
                    >
                </div>
            </section>

            <!-- discard piles: face up, newest first -->
            <section v-for="p in players" :key="`d${p.slot}`" class="space-y-1" :data-testid="`fate-discard-${p.slot}`">
                <h3 class="text-xs font-medium text-muted-foreground">{{ nameOf(p.slot) }}'s discard pile ({{ p.discard.length }})</h3>
                <div v-if="p.discard.length" class="flex max-h-28 flex-wrap gap-1 overflow-y-auto">
                    <component
                        :is="controlled.some((x) => x.slot === p.slot) ? 'button' : 'span'"
                        v-for="c in newestFirst(p.discard)"
                        :key="c"
                        :type="controlled.some((x) => x.slot === p.slot) ? 'button' : undefined"
                        class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        data-testid="fate-discard-card"
                        :data-card="c"
                        :aria-label="controlled.some((x) => x.slot === p.slot) ? `${cardLabel(c)}, in the discard pile` : undefined"
                        @click="controlled.some((x) => x.slot === p.slot) && pick(p.slot, c, 'discard')"
                    >
                        <FateCardFace :card="c" small :highlight="picked?.slot === p.slot && picked.zone === 'discard' && picked.card === c" />
                    </component>
                </div>
                <p v-else class="text-xs text-muted-foreground">Empty.</p>
                <div v-if="p.removed.length" class="flex flex-wrap items-center gap-1" data-testid="fate-removed">
                    <span class="text-xs text-muted-foreground">Removed from the game:</span>
                    <FateCardFace v-for="c in p.removed" :key="c" :card="c" small />
                </div>
            </section>
        </template>

        <FatePeekDialog v-model:open="peekOpen" :cards="peeked" :busy="busy" @arrange="arrange" />
        <FateSearchDialog v-model:open="searchOpen" :cards="searched" :busy="busy" @take="take" />
    </div>
</template>
