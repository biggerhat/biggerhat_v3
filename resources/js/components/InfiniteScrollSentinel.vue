<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        loading?: boolean;
        rootMargin?: string;
    }>(),
    { loading: false, rootMargin: '400px' },
);

const emit = defineEmits<{ (e: 'visible'): void }>();

const target = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

watch(target, (el) => {
    observer?.disconnect();
    if (!el) return;
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting && !props.loading) emit('visible');
        },
        { rootMargin: props.rootMargin },
    );
    observer.observe(el);
});

onUnmounted(() => observer?.disconnect());
</script>

<template>
    <div ref="target" class="h-1 w-full" />
</template>
