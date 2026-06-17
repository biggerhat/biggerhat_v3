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
    mirror?: boolean;
    hideToggle?: boolean;
}>();

const mirrored = ref(props.mirror ?? false);

const suitIconType = computed(() => {
    const s = props.suit.toLowerCase();
    return ['crow', 'mask', 'ram', 'tome'].includes(s) ? s : null;
});

// Full class names so Tailwind picks them up at build time.
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
    <div :class="['relative flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm w-[2.75in] h-[4.75in] text-[8px]', theme.border]">
        <header :class="['flex items-center gap-2 border-b px-3 py-0.5 text-[10px]', theme.header]">
            <span class="inline-flex items-baseline gap-1 font-mono font-bold tabular-nums leading-none text-foreground">
                {{ valueLabel }}<GameIcon v-if="suitIconType" :type="suitIconType" class-name="inline-block" />
            </span>
            <span v-if="name" class="min-w-0 flex-1 truncate text-center font-semibold text-foreground">{{ name }}</span>
            <span v-else class="flex-1"></span>
            <button
                v-if="!hideToggle"
                type="button"
                class="rounded border bg-background/70 px-1.5 py-0.5 text-[8px] font-medium uppercase tracking-wider text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                :title="mirrored ? 'View both sides right-side up' : 'View as printed card (Side B rotated)'"
                @click="mirrored = !mirrored"
            >
                {{ mirrored ? 'Read' : 'Print' }}
            </button>
        </header>

        <section class="flex-1 min-h-0 overflow-hidden space-y-0.5 px-2 py-px">
            <div class="flex items-baseline gap-1.5 font-semibold uppercase tracking-wider text-muted-foreground">
                <span class="rounded bg-primary/15 px-1 py-0">A</span>
                <span v-if="sideA.title" class="min-w-0 truncate font-semibold normal-case tracking-normal text-foreground">{{ sideA.title }}</span>
            </div>
            <LootEffectText
                v-if="sideHasContent(sideA)"
                :text="sideA.effect"
                :abilities="sideA.abilities"
                :actions="sideA.actions"
                :triggers="sideA.triggers"
            />
            <p v-else class="rounded-md border border-dashed bg-muted/10 p-2 italic text-muted-foreground">Side A not yet entered</p>
        </section>

        <div :class="['relative flex items-center justify-center border-y px-3 py-0.5', theme.divider]">
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
                class="relative inline-flex items-center gap-1 rounded border bg-background px-2 py-0.5 font-mono font-semibold uppercase tracking-widest text-muted-foreground"
            >
                <GameIcon v-if="suitIconType" :type="suitIconType" class-name="h-3 inline-block" />
                {{ valueLabel }}
            </span>
        </div>

        <section :class="['flex-1 min-h-0 overflow-hidden space-y-0.5 px-2 py-px', mirrored ? 'rotate-180' : '']">
            <div class="flex items-baseline gap-1.5 font-semibold uppercase tracking-wider text-muted-foreground">
                <span class="rounded bg-primary/15 px-1 py-0 text-primary">B</span>
                <span v-if="sideB.title" class="min-w-0 truncate font-semibold normal-case tracking-normal text-foreground">{{ sideB.title }}</span>
            </div>
            <LootEffectText
                v-if="sideHasContent(sideB)"
                :text="sideB.effect"
                :abilities="sideB.abilities"
                :actions="sideB.actions"
                :triggers="sideB.triggers"
            />
            <p v-else class="rounded-md border border-dashed bg-muted/10 p-2 italic text-muted-foreground">Side B not yet entered</p>
        </section>

        <footer v-if="name" :class="['flex items-center gap-2 border-t px-3 py-0.5 text-[10px]', theme.header, mirrored ? 'rotate-180' : '']">
            <span class="inline-flex items-baseline gap-1 font-mono font-bold tabular-nums leading-none text-foreground">
                {{ valueLabel }}<GameIcon v-if="suitIconType" :type="suitIconType" class-name="inline-block" />
            </span>
            <span class="min-w-0 flex-1 truncate text-center font-semibold text-foreground">{{ name }}</span>
        </footer>
    </div>
</template>
