import { TAROT_RATIO, TAROT_WIDTH_TIERS } from '@/components/CardCreator/utils';
import { computed, nextTick, onMounted, ref, type Ref } from 'vue';

const frame = () => new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

/**
 * For the headless capture pages: both faces always share one exact tarot size (2.75 x 4.75 in proportions, the same as
 * the Bonanza cards). Starts at the smallest tier and steps up through TAROT_WIDTH_TIERS until the taller face's
 * content fits inside it, so cards with more text get a larger tarot card rather than a taller, off-ratio one.
 * Dead space between the text and the border is fine. Only past the largest tier does a face grow taller (and then
 * both match). `ready` flips true once settled; the server-side screenshot waits on it (`data-capture-ready`).
 */
export function useTarotFit(front: Ref<HTMLElement | null>, back: Ref<HTMLElement | null>) {
    const tier = ref(0);
    const overflowHeight = ref(0);
    const ready = ref(false);

    const width = computed(() => TAROT_WIDTH_TIERS[tier.value]);
    const height = computed(() => Math.max(Math.round(width.value * TAROT_RATIO), overflowHeight.value));

    onMounted(async () => {
        await document.fonts?.ready;
        for (;;) {
            await nextTick();
            await frame();
            const natural = Math.max(front.value?.offsetHeight ?? 0, back.value?.offsetHeight ?? 0);
            const fits = natural <= Math.round(width.value * TAROT_RATIO) + 1;
            if (fits) break;
            if (tier.value >= TAROT_WIDTH_TIERS.length - 1) {
                overflowHeight.value = natural;
                break;
            }
            tier.value++;
        }
        await nextTick();
        await frame();
        ready.value = true;
    });

    return { width, height, ready };
}
