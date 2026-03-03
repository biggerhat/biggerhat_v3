import { computed, type Ref } from 'vue';

export function useStaggeredEntry(count: Ref<number>, baseDelay = 50, maxDelay = 600) {
    const delays = computed(() =>
        Array.from({ length: count.value }, (_, i) => ({
            animationDelay: `${Math.min(i * baseDelay, maxDelay)}ms`,
        })),
    );

    return { delays };
}
