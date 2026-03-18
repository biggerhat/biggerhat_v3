<script setup lang="ts">
import { computed, type HTMLAttributes, onMounted, ref } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    type: string;
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();

const currentTheme = ref('dark');
onMounted(() => {
    currentTheme.value = localStorage.theme ?? (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
});

const icons: Record<string, { black: string; white: string; alt: string }> = {
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
};

const icon = computed(() => icons[props.type]);
</script>

<template>
    <template v-if="icon">
        <img v-if="currentTheme === 'light'" :src="icon.black" :class="className" :alt="icon.alt" />
        <img v-else :src="icon.white" :class="className" :alt="icon.alt" />
    </template>
</template>
