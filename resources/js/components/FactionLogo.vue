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
    arcanists: { src: '/images/Factions/M3E-Arcanists.png', alt: 'Arcanists' },
    bayou: { src: '/images/Factions/M3E-Bayou.png', alt: 'Bayou' },
    explorers_society: { src: '/images/Factions/M3E-Explorers.png', alt: "Explorer's Society" },
    guild: { src: '/images/Factions/M3E-Guild.png', alt: 'Guild' },
    neverborn: { src: '/images/Factions/M3E-Neverborn.png', alt: 'Neverborn' },
    outcasts: { src: '/images/Factions/M3E-Outcasts.png', alt: 'Outcasts' },
    resurrectionists: { src: '/images/Factions/M3E-Resurrectionists.png', alt: 'Resurrectionists' },
    ten_thunders: { src: '/images/Factions/M3E-Ten-Thunders.png', alt: 'Ten Thunders' },
};

const info = computed(() => factionMap[props.faction] ?? { src: '', alt: props.faction });
</script>

<template>
    <img :src="info.src" :class="className" :alt="info.alt" loading="lazy" decoding="async" />
</template>
