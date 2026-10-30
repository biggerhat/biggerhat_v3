import { onMounted, ref, useAttrs, useId } from 'vue';

const CONTROLS = 'button, input, select, textarea, [role="combobox"]';

/** The <label> that visually precedes `el`, looking at siblings of `el` and of its first few ancestors. */
function findLabel(el: HTMLElement): HTMLElement | null {
    let node: HTMLElement | null = el;
    for (let depth = 0; depth < 4 && node; depth++) {
        const prev = node.previousElementSibling as HTMLElement | null;
        if (prev) {
            const label = prev.matches('label') ? prev : prev.querySelector<HTMLElement>('label');
            if (label && !prev.querySelector(CONTROLS)) return label;
        }
        node = node.parentElement;
    }

    return null;
}

/**
 * Accessible name for a form control whose <Label> sits next to it without being wired up (the common
 * pattern in this codebase). Once mounted, finds that label and returns an `aria-labelledby` value for the
 * control. An explicit aria-label/aria-labelledby, or a `<label for>` pointing at the control's id, wins.
 *
 * `includeValue` also references the control's own first child span, for components like Select whose
 * current value is rendered there and should be part of the name.
 */
export function useAutoLabel(getEl: () => HTMLElement | undefined, options: { includeValue?: boolean } = {}) {
    const attrs = useAttrs();
    const autoId = useId();
    const labelledBy = ref<string | undefined>();

    onMounted(() => {
        if (attrs['aria-label'] || attrs['aria-labelledby']) return;
        const el = getEl();
        if (!el) return;
        if (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) return;

        const ids: string[] = [];
        const label = findLabel(el);
        if (label) {
            label.id ||= `${autoId}-label`;
            ids.push(label.id);
        }
        if (options.includeValue) {
            const value = el.querySelector<HTMLElement>(':scope > span');
            if (value) {
                value.id ||= `${autoId}-value`;
                ids.push(value.id);
            }
        }
        if (ids.length) {
            el.id ||= `${autoId}-control`;
            labelledBy.value = ids.join(' ');
        }
    });

    return labelledBy;
}
