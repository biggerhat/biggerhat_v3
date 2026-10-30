<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    faction: string;
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();

const factionMap: Record<string, { src: string; alt: string }> = {
    arcanists: { src: '/images/Factions/sm/M3E-Arcanists.webp', alt: 'Arcanists' },
    bayou: { src: '/images/Factions/sm/M3E-Bayou.webp', alt: 'Bayou' },
    explorers_society: { src: '/images/Factions/sm/M3E-Explorers.webp', alt: "Explorer's Society" },
    guild: { src: '/images/Factions/sm/M3E-Guild.webp', alt: 'Guild' },
    neverborn: { src: '/images/Factions/sm/M3E-Neverborn.webp', alt: 'Neverborn' },
    outcasts: { src: '/images/Factions/sm/M3E-Outcasts.webp', alt: 'Outcasts' },
    resurrectionists: { src: '/images/Factions/sm/M3E-Resurrectionists.webp', alt: 'Resurrectionists' },
    ten_thunders: { src: '/images/Factions/sm/M3E-Ten-Thunders.webp', alt: 'Ten Thunders' },
};

const info = computed(() => factionMap[props.faction] ?? { src: '', alt: props.faction });
</script>

<template>
    <img :src="info.src" :class="className" :alt="info.alt" loading="lazy" decoding="async" />
</template>
