import { TAROT_WIDTH_TIERS } from '@/components/CardCreator/utils';
import { useTarotGrowToFit } from '@/composables/useTarotGrowToFit';
import { computed, nextTick, onMounted, onUnmounted, ref, watch, type WatchSource } from 'vue';

/**
 * Live card preview that matches the saved image: the two faces are laid out at the real tarot size the capture page
 * would pick (smallest tier whose content fits, same 2.75 x 4.75 proportion) and the whole thing is scaled down with a
 * CSS transform to whatever width the surrounding column offers. Text therefore wraps exactly as it does on the saved
 * card instead of reflowing to the column width. `sources` are the reactive inputs whose change should re-fit.
 */
export function useScaledTarotPreview(
    getFront: () => HTMLElement | null | undefined,
    getBack: () => HTMLElement | null | undefined,
    sources: WatchSource[],
) {
    const rootEl = ref<HTMLElement | null>(null);
    const available = ref(0);
    const { width, height, measure, debouncedMeasure } = useTarotGrowToFit(getFront, getBack, () => TAROT_WIDTH_TIERS[0]);

    // Never scale up: a roomy column just shows the card at its natural size.
    const scale = computed(() => (available.value > 0 ? Math.min(1, available.value / width.value) : 1));

    watch(sources, () => nextTick(debouncedMeasure), { deep: true });

    let observer: ResizeObserver | null = null;
    onMounted(async () => {
        if (rootEl.value) {
            available.value = rootEl.value.getBoundingClientRect().width;
            observer = new ResizeObserver((entries) => {
                available.value = entries[0]?.contentRect.width ?? available.value;
            });
            observer.observe(rootEl.value);
        }
        await nextTick();
        measure();
        // Web fonts change text metrics, so fit again once they have loaded.
        document.fonts?.ready.then(() => measure());
    });
    onUnmounted(() => observer?.disconnect());

    return { rootEl, width, height, scale };
}
