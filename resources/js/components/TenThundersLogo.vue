<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src='/images/Factions/M3E-Ten-Thunders.png' :class="className" alt="Ten Thunders" />
</template>
