<script setup lang="ts">
import LootEffectText, { type LootAbilityRef, type LootActionRef, type LootTriggerRef } from '@/components/Bonanza/LootEffectText.vue';
import GameIcon from '@/components/GameIcon.vue';
import { computed, ref } from 'vue';

interface LootCardSide {
    title: string | null;
    effect: string | null;
    abilities: LootAbilityRef[];
    actions: LootActionRef[];
    triggers: LootTriggerRef[];
}

const props = defineProps<{
    name: string;
    suit: string;
    valueLabel: string;
    image?: string | null;
    sideA: LootCardSide;
    sideB: LootCardSide;
    // When true, side B is rendered upside-down so the printed card reads
    // correctly from either end. Off by default — readability beats
    // physical fidelity on a webpage.
    mirror?: boolean;
    // Suppress the Print/Read toggle. Used when the card is being captured
    // for image generation — the toggle is purely UI and would clutter
    // the generated PNG.
    hideToggle?: boolean;
}>();

const mirrored = ref(props.mirror ?? false);

// `crow` etc. all resolve to glyphs in GameIcon; joker has no glyph, so
// the value-label pip stands in for the icon in those slots.
const suitIconType = computed(() => {
    const s = props.suit.toLowerCase();
    return ['crow', 'mask', 'ram', 'tome'].includes(s) ? s : null;
});

// Tailwind needs to see the full class names at build time — keep them
// in a static map keyed on the lowercase suit. Mirrors the suit-icon
// colors in GameIcon and the chip tones on the page's filter bar.
const suitThemes: Record<string, { border: string; header: string; divider: string }> = {
    crow: {
        border: 'border-green-500/40 dark:border-green-500/30',
        header: 'bg-green-500/10 dark:bg-green-500/15',
        divider: 'bg-green-500/5 dark:bg-green-500/10',
    },
    mask: {
        border: 'border-purple-500/40 dark:border-purple-500/30',
        header: 'bg-purple-500/10 dark:bg-purple-500/15',
        divider: 'bg-purple-500/5 dark:bg-purple-500/10',
    },
    ram: {
        border: 'border-red-500/40 dark:border-red-500/30',
        header: 'bg-red-500/10 dark:bg-red-500/15',
        divider: 'bg-red-500/5 dark:bg-red-500/10',
    },
    tome: {
        border: 'border-blue-500/40 dark:border-blue-500/30',
        header: 'bg-blue-500/10 dark:bg-blue-500/15',
        divider: 'bg-blue-500/5 dark:bg-blue-500/10',
    },
    joker: {
        border: 'border-amber-500/40 dark:border-amber-500/30',
        header: 'bg-amber-500/10 dark:bg-amber-500/15',
        divider: 'bg-amber-500/5 dark:bg-amber-500/10',
    },
};

const theme = computed(() => suitThemes[props.suit.toLowerCase()] ?? { border: 'border-border', header: 'bg-secondary/40', divider: 'bg-muted/30' });

const sideHasContent = (side: LootCardSide): boolean =>
    !!side.title || !!side.effect || side.abilities.length > 0 || side.actions.length > 0 || side.triggers.length > 0;
</script>

<template>
    <!-- Card frame: rounded, bordered. Both sides stack vertically inside;
         the middle divider carries the loot card's image (or value/suit pip
         fallback) so the card has a focal centerpiece even when the sides
         are sparse. -->
    <div :class="['relative flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm', theme.border]">
        <!-- Top header: value+suit pip on the left, card name centered,
             print/read toggle on the right. The same name+pip pair appears
             on the bottom footer so the card reads correctly from either
             end when viewed in Print mode. -->
        <header :class="['flex items-center gap-2 border-b px-3 py-1.5', theme.header]">
            <span class="inline-flex items-baseline gap-1 font-mono text-lg font-bold tabular-nums leading-none text-foreground">
                {{ valueLabel }}<GameIcon v-if="suitIconType" :type="suitIconType" class-name="h-5 inline-block" />
            </span>
            <span v-if="name" class="min-w-0 flex-1 truncate text-center text-sm font-semibold text-foreground">{{ name }}</span>
            <span v-else class="flex-1"></span>
            <button
                v-if="!hideToggle"
                type="button"
                class="rounded border bg-background/70 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wider text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                :title="mirrored ? 'View both sides right-side up' : 'View as printed card (Side B rotated)'"
                @click="mirrored = !mirrored"
            >
                {{ mirrored ? 'Read' : 'Print' }}
            </button>
        </header>

        <!-- SIDE A -->
        <section class="space-y-1.5 px-3 py-2.5">
            <div class="flex items-baseline gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                <span class="rounded bg-primary/15 px-1 py-0 text-primary">A</span>
                <span v-if="sideA.title" class="truncate text-sm font-semibold normal-case tracking-normal text-foreground">{{ sideA.title }}</span>
            </div>
            <LootEffectText
                v-if="sideHasContent(sideA)"
                :text="sideA.effect"
                :abilities="sideA.abilities"
                :actions="sideA.actions"
                :triggers="sideA.triggers"
            />
            <p v-else class="rounded-md border border-dashed bg-muted/10 p-2 text-[11px] italic text-muted-foreground">Side A not yet entered</p>
        </section>

        <!-- Divider — slimmer than before; the image is the focal point if
             one exists, otherwise we fall back to a subtle suit/value chip. -->
        <div :class="['relative flex items-center justify-center border-y px-3 py-1.5', theme.divider]">
            <div class="absolute inset-x-3 top-1/2 h-px bg-border/60" />
            <img
                v-if="image"
                :src="`/storage/${image}`"
                :alt="name"
                class="relative size-10 rounded-md border bg-background object-cover shadow-sm"
                loading="lazy"
            />
            <span
                v-else
                class="relative inline-flex items-center gap-1 rounded border bg-background px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-widest text-muted-foreground"
            >
                <GameIcon v-if="suitIconType" :type="suitIconType" class-name="h-3 inline-block" />
                {{ valueLabel }}
            </span>
        </div>

        <!-- SIDE B (right-side up by default; rotated 180° in print view) -->
        <section :class="['space-y-1.5 px-3 py-2.5', mirrored ? 'rotate-180' : '']">
            <div class="flex items-baseline gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                <span class="rounded bg-primary/15 px-1 py-0 text-primary">B</span>
                <span v-if="sideB.title" class="truncate text-sm font-semibold normal-case tracking-normal text-foreground">{{ sideB.title }}</span>
            </div>
            <LootEffectText
                v-if="sideHasContent(sideB)"
                :text="sideB.effect"
                :abilities="sideB.abilities"
                :actions="sideB.actions"
                :triggers="sideB.triggers"
            />
            <p v-else class="rounded-md border border-dashed bg-muted/10 p-2 text-[11px] italic text-muted-foreground">Side B not yet entered</p>
        </section>

        <!-- Bottom footer: card name (paragraph-style, matches body text)
             plus a mirrored value+suit pip so the card has matching corners.
             Rotates 180° in print view so it reads upright when the card
             is physically flipped (i.e. the bottom becomes the top). -->
        <footer v-if="name" :class="['flex items-center justify-between gap-2 border-t px-3 py-1.5', theme.header, mirrored ? 'rotate-180' : '']">
            <span class="inline-flex items-baseline gap-1 font-mono text-lg font-bold tabular-nums leading-none text-foreground">
                {{ valueLabel }}<GameIcon v-if="suitIconType" :type="suitIconType" class-name="h-5 inline-block" />
            </span>
            <span class="min-w-0 flex-1 truncate text-center text-sm font-semibold text-foreground">{{ name }}</span>
            <!-- Spacer to keep the name visually centered between the two ends. -->
            <span class="invisible inline-flex items-baseline gap-1 font-mono text-lg font-bold leading-none">
                {{ valueLabel }}<GameIcon v-if="suitIconType" :type="suitIconType" class-name="h-5 inline-block" />
            </span>
        </footer>
    </div>
</template>
