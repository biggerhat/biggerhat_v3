<script setup lang="ts">
import { type HTMLAttributes, onMounted, ref } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const currentTheme = ref('dark');
onMounted(() => {
    currentTheme.value = localStorage.theme ?? (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
});
</script>

<template>
    <img v-if="currentTheme === 'light'" src="/images/Symbols/M4E-Symbol_Soulstone-Black.png" :class="className" alt="Soulstone" loading="lazy" decoding="async" />
    <img v-else src="/images/Symbols/M4E-Symbol_Soulstone-White.png" :class="className" alt="Soulstone" loading="lazy" decoding="async" />
</template>
