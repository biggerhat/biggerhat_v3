<script setup lang="ts">
import { computed } from 'vue';
import {HTMLAttributes} from "vue";

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const currentTheme = computed(() => {
    return localStorage.theme;
});
</script>

<template>
    <img v-if="currentTheme === 'light'" src='/images/Symbols/M4E-Symbol_Soulstone-Black.png' :class="className" alt="Soulstone" />
    <img v-else src='/images/Symbols/M4E-Symbol_Soulstone-White.png' :class="className" alt="Soulstone" />
</template>
