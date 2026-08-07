import { TAROT_RATIO, TAROT_WIDTH_TIERS } from '@/components/CardCreator/utils';
import { useDebounceFn } from '@vueuse/core';
import { nextTick, ref } from 'vue';

/**
 * Grows a two-sided (front/back) card's box to fit its actual rendered
 * content, tier by tier, keeping the tarot 550:950 proportion at every step
 * — unlike the old shrinkToFit approach (font-shrink to a hard floor, then
 * clip past it) or a height-only grow (which produces a tall, skewed card
 * instead of a bigger tarot card).
 *
 * Algorithm per measure(): starting from `guessWidth()` (a cheap char-count
 * estimate — just picks a sane starting tier, doesn't need to be exact),
 * apply each width tier, wait for the DOM to reflow, and check whether the
 * taller of front/back's real scrollHeight fits within that tier's
 * proportioned height at the ACTUAL rendered width (which can be narrower
 * than the tier itself in a constrained embedding — read via
 * getBoundingClientRect rather than assumed). Stops at the first tier that
 * fits. If content still doesn't fit at the largest tier (a pathologically
 * dense card, or a very narrow embedding), height grows past the tarot
 * proportion as a last resort rather than clip.
 *
 * Takes getter functions rather than Refs so callers can point at a nested
 * component's exposed root element (e.g. `() => printFrontRef.value?.rootRef`)
 * as easily as a plain template ref.
 */
export function useTarotGrowToFit(
    getFrontEl: () => HTMLElement | null | undefined,
    getBackEl: () => HTMLElement | null | undefined,
    guessWidth: () => number,
) {
    const width = ref(guessWidth());
    const height = ref(Math.round(width.value * TAROT_RATIO));

    const measure = async () => {
        const startIndex = Math.max(0, TAROT_WIDTH_TIERS.indexOf(guessWidth()));
        for (let i = startIndex; i < TAROT_WIDTH_TIERS.length; i++) {
            width.value = TAROT_WIDTH_TIERS[i];
            // Reset height to THIS tier's own natural proportion before
            // measuring — not left over from wherever the previous measure()
            // call settled. height feeds back into cardMinHeight on the
            // faces being measured, so a stale (often larger) leftover value
            // would force scrollHeight artificially tall here, making every
            // early tier look like it "doesn't fit" and ratcheting the size
            // up permanently across calls instead of tracking real content.
            height.value = Math.round(width.value * TAROT_RATIO);
            await nextTick();
            const neededHeight = Math.max(getFrontEl()?.scrollHeight ?? 0, getBackEl()?.scrollHeight ?? 0);
            const actualWidth = getFrontEl()?.getBoundingClientRect().width || width.value;
            const availableHeight = actualWidth * TAROT_RATIO;
            if (neededHeight <= availableHeight + 1) {
                height.value = Math.round(availableHeight);
                return;
            }
            if (i === TAROT_WIDTH_TIERS.length - 1) {
                height.value = neededHeight;
            }
        }
    };

    const debouncedMeasure = useDebounceFn(measure, 150);

    return { width, height, measure, debouncedMeasure };
}
