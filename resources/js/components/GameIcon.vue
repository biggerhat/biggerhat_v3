<script setup lang="ts">
import { computed, HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    type: string;
    className?: HTMLAttributes['class'];
    /** Font-size relative to the current text context, in em. The
     * M4E-Symbols glyphs are drawn with substantial internal padding, so a
     * literal 1em renders visibly smaller than surrounding text — confirmed
     * via screenshot at roughly half height at 1em, and still visibly
     * *larger* than surrounding text at 1.75em, so 1.3 splits the
     * difference. Applied via inline style rather than a Tailwind text-*
     * class so it always wins regardless of what a caller passes through
     * `className` (some older call sites still pass a now-superseded
     * text-sm/text-xs for sizing; inline style beats those unconditionally). */
    sizeEm?: number;
}

const props = withDefaults(defineProps<Props>(), { sizeEm: 1.3 });

const icons: Record<string, { glyph: string; alt: string; color?: string }> = {
    // Suits
    crow: { glyph: 'c', alt: 'Crow', color: 'text-green-700 dark:text-green-400' },
    crows: { glyph: 'c', alt: 'Crow', color: 'text-green-700 dark:text-green-400' },
    mask: { glyph: 'm', alt: 'Mask', color: 'text-purple-700 dark:text-purple-400' },
    masks: { glyph: 'm', alt: 'Mask', color: 'text-purple-700 dark:text-purple-400' },
    ram: { glyph: 'r', alt: 'Ram', color: 'text-red-600 dark:text-red-400' },
    rams: { glyph: 'r', alt: 'Ram', color: 'text-red-600 dark:text-red-400' },
    tome: { glyph: 't', alt: 'Tome', color: 'text-blue-600 dark:text-blue-400' },
    tomes: { glyph: 't', alt: 'Tome', color: 'text-blue-600 dark:text-blue-400' },
    soulstone: { glyph: 's', alt: 'Soulstone' },
    soulstones: { glyph: 's', alt: 'Soulstone' },

    // Range Types
    melee: { glyph: 'y', alt: 'Melee' },
    missile: { glyph: 'z', alt: 'Missile' },
    magic: { glyph: 'q', alt: 'Magic' },
    pulse: { glyph: 'p', alt: 'Pulse' },

    // Modifiers
    positive: { glyph: '+', alt: 'Positive' },
    negative: { glyph: '-', alt: 'Negative' },

    // Defensive Types
    physical_defense: { glyph: 'u', alt: 'Physical Defense' },
    magical_defense: { glyph: 'x', alt: 'Magical Defense' },
    unusual_defense: { glyph: 'v', alt: 'Unusual Defense' },

    // Signature Action
    signature_action: { glyph: 'f', alt: 'Signature Action' },
};

const icon = computed(() => icons[props.type.toLowerCase()]);
</script>

<template>
    <span
        v-if="icon"
        :class="[className, icon.color]"
        :style="{ fontSize: sizeEm + 'em' }"
        class="game-icon font-['M4E-Symbols'] font-normal leading-none"
        role="img"
        :aria-label="icon.alt"
        >{{ icon.glyph }}</span
    >
</template>
