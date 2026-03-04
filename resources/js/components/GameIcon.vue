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

const currentTheme = computed(() => {
    return localStorage.theme;
});

const icons: Record<string, { black: string; white: string; alt: string }> = {
    // Suits
    crow: {
        black: '/images/Symbols/M4E-Symbol_Crow-Black.png',
        white: '/images/Symbols/M4E-Symbol_Crow-White.png',
        alt: 'Crow',
    },
    mask: {
        black: '/images/Symbols/M4E-Symbol_Mask-Black.png',
        white: '/images/Symbols/M4E-Symbol_Mask-White.png',
        alt: 'Mask',
    },
    ram: {
        black: '/images/Symbols/M4E-Symbol_Ram-Black.png',
        white: '/images/Symbols/M4E-Symbol_Ram-White.png',
        alt: 'Ram',
    },
    tome: {
        black: '/images/Symbols/M4E-Symbol_Tome-Black.png',
        white: '/images/Symbols/M4E-Symbol_Tome-White.png',
        alt: 'Tome',
    },
    soulstone: {
        black: '/images/Symbols/M4E-Symbol_Soulstone-Black.png',
        white: '/images/Symbols/M4E-Symbol_Soulstone-White.png',
        alt: 'Soulstone',
    },

    // Range Types
    melee: {
        black: '/images/Symbols/M4E-Symbol_Melee-Black.png',
        white: '/images/Symbols/M4E-Symbol_Melee-White.png',
        alt: 'Melee',
    },
    missile: {
        black: '/images/Symbols/M4E-Symbol_Missile-Black.png',
        white: '/images/Symbols/M4E-Symbol_Missile-White.png',
        alt: 'Missile',
    },
    magic: {
        black: '/images/Symbols/M4E-Symbol_Magic-Black.png',
        white: '/images/Symbols/M4E-Symbol_Magic-White.png',
        alt: 'Magic',
    },
    pulse: {
        black: '/images/Symbols/M4E-Symbol_Pulse-Black.png',
        white: '/images/Symbols/M4E-Symbol_Pulse-White.png',
        alt: 'Pulse',
    },

    // Modifiers
    positive: {
        black: '/images/Symbols/M4E-Symbol_Positive-Black.png',
        white: '/images/Symbols/M4E-Symbol_Positive-White.png',
        alt: 'Positive',
    },
    negative: {
        black: '/images/Symbols/M4E-Symbol_Negative-Black.png',
        white: '/images/Symbols/M4E-Symbol_Negative-White.png',
        alt: 'Negative',
    },

    // Defensive Types
    physical_defense: {
        black: '/images/Symbols/M4E-Symbol_Physical-Def-Black.png',
        white: '/images/Symbols/M4E-Symbol_Physical-Def-White.png',
        alt: 'Physical Defense',
    },
    magical_defense: {
        black: '/images/Symbols/M4E-Symbol_Magical-Def-Black.png',
        white: '/images/Symbols/M4E-Symbol_Magical-Def-White.png',
        alt: 'Magical Defense',
    },
    unusual_defense: {
        black: '/images/Symbols/M4E-Symbol_Unusual-Def-Black.png',
        white: '/images/Symbols/M4E-Symbol_Unusual-Def-White.png',
        alt: 'Unusual Defense',
    },

    // Signature Action
    signature_action: {
        black: '/images/Symbols/M4E-Symbol_Signature-Action-Black.png',
        white: '/images/Symbols/M4E-Symbol_Signature-Action-White.png',
        alt: 'Signature Action',
    },
};

const icon = computed(() => icons[props.type]);
</script>

<template>
    <template v-if="icon">
        <img v-if="currentTheme === 'light'" :src="icon.black" :class="className" :alt="icon.alt" />
        <img v-else :src="icon.white" :class="className" :alt="icon.alt" />
    </template>
</template>
