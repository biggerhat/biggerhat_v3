import { ref } from 'vue';

export interface ConfirmOptions {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Style the confirm button as destructive (red). */
    destructive?: boolean;
}

interface ConfirmState {
    open: boolean;
    options: ConfirmOptions;
    resolve: ((value: boolean) => void) | null;
}

const state = ref<ConfirmState>({
    open: false,
    options: { message: '' },
    resolve: null,
});

/**
 * Promise-based replacement for `window.confirm`. The matching ConfirmDialog
 * component (mounted once in AppShell) reads the singleton state and renders
 * the actual dialog using the project's Dialog UI primitives.
 *
 * Usage:
 *   const confirm = useConfirm();
 *   if (!(await confirm('Delete this?'))) return;
 *   if (!(await confirm({ title: 'Force', message: '…', destructive: true }))) return;
 */
export function useConfirm() {
    return (input: ConfirmOptions | string): Promise<boolean> => {
        const options: ConfirmOptions = typeof input === 'string' ? { message: input } : input;
        return new Promise((resolve) => {
            state.value = { open: true, options, resolve };
        });
    };
}

// --- Internals consumed by ConfirmDialog.vue. Not part of the public API.
export function _confirmState() {
    return state;
}

export function _resolveConfirm(value: boolean) {
    state.value.resolve?.(value);
    state.value = { open: false, options: state.value.options, resolve: null };
}
