<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
    /** Empty when the logo sits next to the site name as text, so screen readers don't hear it twice. */
    alt?: string;
}

withDefaults(defineProps<Props>(), { alt: 'BiggerHat' });
</script>

<template>
    <img src="/images/hat_cropped_sm.webp" :class="className" :alt="alt" />
</template>
