import { ref } from 'vue';

export interface PromptOptions {
    title?: string;
    message?: string;
    /** Pre-fill value the user can edit. */
    defaultValue?: string;
    placeholder?: string;
    confirmLabel?: string;
    cancelLabel?: string;
}

interface PromptState {
    open: boolean;
    options: PromptOptions;
    resolve: ((value: string | null) => void) | null;
}

const state = ref<PromptState>({
    open: false,
    options: {},
    resolve: null,
});

/**
 * Promise-based replacement for `window.prompt`. Resolves to the entered
 * string, or `null` when the user cancels (matching window.prompt's contract).
 *
 * Usage:
 *   const prompt = usePrompt();
 *   const url = await prompt({ title: 'Link URL', defaultValue: existing });
 *   if (url === null) return;
 */
export function usePrompt() {
    return (input: PromptOptions | string): Promise<string | null> => {
        const options: PromptOptions = typeof input === 'string' ? { message: input } : input;
        return new Promise((resolve) => {
            // If a previous prompt is still open (rare — the dialog is modal,
            // but a programmatic re-open can race), resolve its promise with
            // null first so callers don't end up with permanently-pending
            // awaits.
            const pending = state.value.resolve;
            if (pending) {
                pending(null);
            }
            state.value = { open: true, options, resolve };
        });
    };
}

// --- Internals consumed by PromptDialog.vue. Not part of the public API.
export function _promptState() {
    return state;
}

export function _resolvePrompt(value: string | null) {
    state.value.resolve?.(value);
    state.value = { open: false, options: state.value.options, resolve: null };
}
