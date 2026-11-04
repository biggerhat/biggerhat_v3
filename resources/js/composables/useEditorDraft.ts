import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Options<T> {
    /** localStorage key, one per editor type (e.g. `bh_draft_card`). */
    key: string;
    /** Current editable state as plain JSON-serialisable data. */
    snapshot: () => T;
    /** Apply a stored snapshot back onto the editor state. */
    restore: (value: T) => void;
    /** Drafts are only kept for brand-new cards; editing an existing one just gets the leave warning. */
    keepDraft: boolean;
}

/**
 * Protects work in the card editors: warns before the tab is closed with unsaved changes and, for new cards,
 * keeps a debounced draft in localStorage that can be restored after an accidental reload or navigation.
 * Storage can be blocked (private windows), so every access is guarded and the editor works without it.
 */
export function useEditorDraft<T>({ key, snapshot, restore, keepDraft }: Options<T>) {
    const hasDraft = ref(false);
    const dirty = ref(false);
    let baseline = '';
    let timer: ReturnType<typeof setTimeout> | undefined;

    const read = (): string | null => {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    };
    const write = (value: string | null) => {
        try {
            if (value === null) window.localStorage.removeItem(key);
            else window.localStorage.setItem(key, value);
        } catch {
            // storage unavailable — drafts are a convenience only
        }
    };

    const onBeforeUnload = (e: BeforeUnloadEvent) => {
        if (!dirty.value) return;
        e.preventDefault();
        e.returnValue = '';
    };

    onMounted(() => {
        baseline = JSON.stringify(snapshot());
        if (keepDraft) {
            const stored = read();
            hasDraft.value = !!stored && stored !== baseline;
        }
        window.addEventListener('beforeunload', onBeforeUnload);

        watch(
            snapshot,
            () => {
                const current = JSON.stringify(snapshot());
                dirty.value = current !== baseline;
                if (!keepDraft) return;
                clearTimeout(timer);
                timer = setTimeout(() => {
                    // Don't overwrite an unanswered "restore draft?" offer with the empty form.
                    if (hasDraft.value) return;
                    write(dirty.value ? current : null);
                }, 800);
            },
            { deep: true },
        );
    });

    onBeforeUnmount(() => {
        clearTimeout(timer);
        window.removeEventListener('beforeunload', onBeforeUnload);
    });

    const restoreDraft = () => {
        const stored = read();
        if (stored) {
            try {
                restore(JSON.parse(stored) as T);
            } catch {
                write(null);
            }
        }
        hasDraft.value = false;
    };

    const discardDraft = () => {
        write(null);
        hasDraft.value = false;
    };

    /** Call after a successful save so the leave warning and stored draft go away. */
    const markSaved = () => {
        baseline = JSON.stringify(snapshot());
        dirty.value = false;
        clearTimeout(timer);
        write(null);
    };

    // Same state, for JSON export / import (see useCardJsonTransfer).
    return { hasDraft, dirty, restoreDraft, discardDraft, markSaved, currentSnapshot: snapshot, applySnapshot: restore };
}
