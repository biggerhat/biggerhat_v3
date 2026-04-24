<script setup lang="ts">
import { computed, HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    type: string;
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();

// Mapping derived from the TOS rulebook font (`1E-TOS-Symbols.ttf`), which
// ships 15 mapped glyphs — each keyed to a single ASCII codepoint.
const icons: Record<string, { glyph: string; alt: string; color?: string }> = {
    // Suits (shared with Malifaux — same tint convention)
    crow: { glyph: 'c', alt: 'Crow', color: 'text-green-700 dark:text-green-400' },
    crows: { glyph: 'c', alt: 'Crow', color: 'text-green-700 dark:text-green-400' },
    mask: { glyph: 'M', alt: 'Mask', color: 'text-purple-700 dark:text-purple-400' },
    masks: { glyph: 'M', alt: 'Mask', color: 'text-purple-700 dark:text-purple-400' },
    ram: { glyph: 'R', alt: 'Ram', color: 'text-red-600 dark:text-red-400' },
    rams: { glyph: 'R', alt: 'Ram', color: 'text-red-600 dark:text-red-400' },
    tome: { glyph: 't', alt: 'Tome', color: 'text-blue-600 dark:text-blue-400' },
    tomes: { glyph: 't', alt: 'Tome', color: 'text-blue-600 dark:text-blue-400' },

    // Ranges / areas
    melee: { glyph: 'y', alt: 'Melee' },
    missile: { glyph: 'z', alt: 'Missile' },
    pulse: { glyph: 'p', alt: 'Pulse' },
    aura: { glyph: 'a', alt: 'Aura' },

    // TOS-specific
    magic: { glyph: '#', alt: 'Magic', color: 'text-purple-700 dark:text-purple-400' },
    morale: { glyph: '!', alt: 'Morale', color: 'text-amber-600 dark:text-amber-400' },
    turncard: { glyph: '@', alt: 'Turn Card' },

    // Margin triggers — numeric cost shown inside the duel icon
    margin_3: { glyph: '3', alt: 'Margin 3' },
    margin_4: { glyph: '4', alt: 'Margin 4' },
    margin_5: { glyph: '5', alt: 'Margin 5' },
    // Token aliases matching the rulebook shorthand
    '3margin': { glyph: '3', alt: 'Margin 3' },
    '4margin': { glyph: '4', alt: 'Margin 4' },
    '5margin': { glyph: '5', alt: 'Margin 5' },
};

const icon = computed(() => icons[props.type.toLowerCase()]);
</script>

<template>
    <span
        v-if="icon"
        :class="[className, icon.color]"
        class="tos-icon font-['TOS-Symbols'] font-normal leading-none"
        role="img"
        :aria-label="icon.alt"
        >{{ icon.glyph }}</span
    >
</template>
