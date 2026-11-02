import { type Ref, watch } from 'vue';

export interface ShrinkToFitOptions {
    maxFontSize: number;
    minFontSize: number;
    step?: number;
    /** Debounce (ms) applied to re-measures after the first one — `source`
     *  is typically bound to live-editable fields (ability/action text), so
     *  without this every keystroke would re-run the full shrink loop (up
     *  to ~14 forced reflows at the default 0.5px step) on the same input
     *  event. The very first measurement (on mount) always runs immediately
     *  so there's no visible flash of unshrunk text. */
    debounceMs?: number;
}

/**
 * Shrinks `target`'s font-size (in px, via inline style) until its content
 * fits its own box (scrollHeight <= clientHeight) or `minFontSize` is hit.
 * Re-measures whenever `source` changes, after Vue has flushed the DOM
 * update the new value produced — real overflow measurement rather than a
 * char-count heuristic, so it only shrinks as much as the actual content
 * requires. Debounced after the first run — see debounceMs above.
 */
export function useShrinkToFit(target: Ref<HTMLElement | null | undefined>, source: () => unknown, options: ShrinkToFitOptions): void {
    const { maxFontSize, minFontSize, step = 0.5, debounceMs = 150 } = options;

    let timer: ReturnType<typeof setTimeout> | null = null;
    let isFirstRun = true;

    const measure = () => {
        const el = target.value;
        if (!el) return;

        // Fit is monotonic in font size (smaller text never overflows more), so binary-search the step grid
        // instead of walking it: ~4-5 forced reflows instead of up to ~14, same resulting size.
        const sizeAt = (k: number) => Math.max(minFontSize, maxFontSize - k * step);
        const fits = (k: number) => {
            el.style.fontSize = `${sizeAt(k)}px`;
            return el.scrollHeight <= el.clientHeight;
        };

        const last = Math.ceil((maxFontSize - minFontSize) / step);
        let lo = 0;
        let hi = last; // the smallest size always "fits" — it's the floor, whether or not the text really does
        while (lo < hi) {
            const mid = Math.floor((lo + hi) / 2);
            if (fits(mid)) hi = mid;
            else lo = mid + 1;
        }
        el.style.fontSize = `${sizeAt(lo)}px`;
    };

    watch(
        source,
        () => {
            if (isFirstRun) {
                isFirstRun = false;
                measure();
                return;
            }
            if (timer) clearTimeout(timer);
            timer = setTimeout(measure, debounceMs);
        },
        { immediate: true, flush: 'post' },
    );
}
