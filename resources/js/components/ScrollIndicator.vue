<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
    containerId: string;
    total: number;
}

const props = defineProps<Props>();

const currentIndex = ref(1);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    const container = document.getElementById(props.containerId);
    if (!container) return;

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    const idx = Number((entry.target as HTMLElement).dataset.index);
                    if (!isNaN(idx)) {
                        currentIndex.value = idx + 1;
                    }
                }
            }
        },
        {
            root: container,
            threshold: 0.5,
        },
    );

    const items = container.querySelectorAll('[data-index]');
    items.forEach((el) => observer?.observe(el));
});

onUnmounted(() => {
    observer?.disconnect();
});
</script>

<template>
    <div v-if="total > 0" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 md:hidden">
        <div class="rounded-full bg-primary/80 px-3 py-1 text-xs font-medium text-primary-foreground backdrop-blur-sm">
            {{ currentIndex }} / {{ total }}
        </div>
    </div>
</template>
