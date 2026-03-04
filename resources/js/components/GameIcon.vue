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

const icons: Record<string, { glyph: string; alt: string }> = {
    // Suits
    crow: { glyph: 'c', alt: 'Crow' },
    mask: { glyph: 'm', alt: 'Mask' },
    ram: { glyph: 'r', alt: 'Ram' },
    tome: { glyph: 't', alt: 'Tome' },
    soulstone: { glyph: 's', alt: 'Soulstone' },

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

const icon = computed(() => icons[props.type]);
</script>

<template>
    <span
        v-if="icon"
        :class="className"
        class="game-icon font-['M4E-Symbols'] leading-none"
        role="img"
        :aria-label="icon.alt"
        >{{ icon.glyph }}</span
    >
</template>
